<?php

namespace App\Http\Controllers;

use App\Models\ChecklistMaster;
use App\Models\ChecklistItem;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Class_ChecklistLog;
use App\Models\ChecklistLog;

class ChecklistMasterController extends Controller
{
    /**
     * Menampilkan semua data master checklist beserta item-itemnya.
     */
    public function index()
    {
        return ChecklistMaster::with(['items', 'type'])->latest()->get();
    }

    /**
     * Menyimpan master checklist baru beserta item-itemnya.
     */
    public function store(Request $request)
    {
        $cleanedIdKaryawan = str_replace(['"', '\\'], '', $request->id_karyawan);
        $request->merge(['id_karyawan' => $cleanedIdKaryawan]);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'checklist_type_id' => 'required|integer|exists:checklist_types,id',
            'id_karyawan' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.activity_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        // --- LOGIKA SINKRONISASI USER BARU YANG LEBIH EKSPLISIT ---
        try {
            $apiService = new API_Service();
            $requestAPIService = ['id_karyawan' => $request->id_karyawan];
            $dataKaryawanResponse = $apiService->getDataKaryawan($requestAPIService);

            if (isset($dataKaryawanResponse[0])) {
                $detailKaryawan = $dataKaryawanResponse[0];
                $namaKaryawan = $detailKaryawan['name'] ?? 'User ' . $request->id_karyawan;
                $emailKaryawan = $detailKaryawan['email'] ?? $request->id_karyawan . '@internal.com';

                $user = User::updateOrCreate(
                    ['karyawan_id' => $request->id_karyawan],
                    [
                        'name' => $namaKaryawan,
                        'email' => $emailKaryawan,
                    ]
                );

                if ($user->wasRecentlyCreated) {
                    $user->password = bcrypt(Str::random(10));
                    $user->save();
                }
            } else {
                throw new \Exception('Respons API LokaHR tidak valid atau data karyawan tidak ditemukan.');
            }
        } catch (\Exception $e) {
            Log::error('Gagal sinkronisasi data karyawan dari API LokaHR.', [
                'id_karyawan' => $request->id_karyawan,
                'error' => $e->getMessage(),
                'api_response' => $dataKaryawanResponse ?? 'Tidak ada respons'
            ]);

            $user = User::firstOrCreate(
                ['karyawan_id' => $request->id_karyawan],
                [
                    'name' => 'User ' . $request->id_karyawan,
                    'email' => $request->id_karyawan . '@internal.com',
                    'password' => bcrypt(Str::random(10))
                ]
            );
        }

        return DB::transaction(function () use ($request, $user) {
            $master = ChecklistMaster::create([
                'checklist_id' => 'CK-' . strtoupper(Str::random(8)),
                'name' => $request->name,
                'checklist_type_id' => $request->checklist_type_id,
                'created_by' => $user->id,
            ]);

            $itemNames = [];
            foreach ($request->items as $index => $itemData) {
                ChecklistItem::create([
                    'checklist_master_id' => $master->id,
                    'activity_name' => $itemData['activity_name'],
                    'order' => $index + 1,
                    'is_required' => $itemData['is_required'] ?? true,
                ]);
                $itemNames[] = $itemData['activity_name'];
            }

            // Log create master checklist dengan detail items
            $requestLog = [
                'checklist_master_id' => $master->id,
                'user_id' => $user->karyawan_id,
                'name' => $user->name,
                'activity' => 'Create Master Checklist',
                'detail_act' => 'Membuat master checklist "' . $master->name . '" dengan ' . count($itemNames) . ' item: [' . implode(', ', $itemNames) . ']',
            ];

            $logController = new Class_ChecklistLog();
            $logController->insert($requestLog);

            return response()->json([
                'status' => 'success',
                'message' => 'Master checklist berhasil dibuat.',
                'data' => $master->load('items')
            ], 201);
        });
    }

    /**
     * Menampilkan satu data master checklist spesifik.
     */
    public function show(ChecklistMaster $checklistMaster)
    {
        // return $checklistMaster->load('items');
        return $checklistMaster->load('items', 'type', 'creator');
    }

    /**
     * Mengupdate data master checklist dengan detailed logging.
     */
    public function update(Request $request, ChecklistMaster $checklistMaster)
    {
        // Bersihkan id_karyawan jika ada
        if ($request->has('id_karyawan')) {
            $cleanedIdKaryawan = str_replace(['"', '\\'], '', $request->id_karyawan);
            $request->merge(['id_karyawan' => $cleanedIdKaryawan]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'checklist_type_id' => 'sometimes|required|integer|exists:checklist_types,id',
            'id_karyawan' => 'nullable|string|exists:users,karyawan_id',
            'items' => 'sometimes|required|array|min:1',
            'items.*.id' => 'nullable|integer|exists:checklist_items,id',
            'items.*.activity_name' => 'required|string|max:255',
            'items.*.is_required' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        // Tentukan user untuk logging
        $userForLogging = $this->determineUserForLogging($request, $checklistMaster);

        // Simpan data lama untuk comparison
        $oldMasterData = [
            'name' => $checklistMaster->name,
            'checklist_type_id' => $checklistMaster->checklist_type_id
        ];

        // Ambil semua items lama untuk comparison
        $oldItems = $checklistMaster->items->keyBy('id')->toArray();

        return DB::transaction(function () use ($request, $checklistMaster, $userForLogging, $oldMasterData, $oldItems) {
            $logEntries = [];

            // === UPDATE MASTER CHECKLIST ===
            $updateData = $request->only(['name', 'checklist_type_id']);
            if (!empty($updateData)) {
                $checklistMaster->update($updateData);

                // Log perubahan nama
                if (isset($updateData['name']) && $updateData['name'] !== $oldMasterData['name']) {
                    $logEntries[] = [
                        'activity' => 'Update Master Checklist Name',
                        'detail_act' => "Mengubah nama master checklist dari '{$oldMasterData['name']}' menjadi '{$updateData['name']}'"
                    ];
                }

                // Log perubahan tipe
                if (isset($updateData['checklist_type_id']) && $updateData['checklist_type_id'] !== $oldMasterData['checklist_type_id']) {
                    $logEntries[] = [
                        'activity' => 'Update Master Checklist Type',
                        'detail_act' => "Mengubah tipe checklist dari ID {$oldMasterData['checklist_type_id']} menjadi ID {$updateData['checklist_type_id']}"
                    ];
                }
            }

            // === UPDATE ITEMS ===
            if ($request->has('items')) {
                $incomingItems = $request->input('items', []);
                $incomingItemIds = array_filter(array_column($incomingItems, 'id'));

                // 1. HAPUS ITEMS yang tidak ada di request baru
                $itemsToDelete = ChecklistItem::where('checklist_master_id', $checklistMaster->id)
                    ->whereNotIn('id', $incomingItemIds)
                    ->get();

                foreach ($itemsToDelete as $deletedItem) {
                    $logEntries[] = [
                        'activity' => 'Delete Checklist Item',
                        'detail_act' => "Menghapus item '{$deletedItem->activity_name}' dari checklist '{$checklistMaster->name}'"
                    ];
                }
                $itemsToDelete->each->delete();

                // 2. UPDATE/CREATE ITEMS
                foreach ($incomingItems as $index => $itemData) {
                    $itemId = $itemData['id'] ?? null;

                    if ($itemId && isset($oldItems[$itemId])) {
                        // === UPDATE EXISTING ITEM ===
                        $oldItem = $oldItems[$itemId];
                        $changes = [];

                        // Cek perubahan nama activity
                        if ($itemData['activity_name'] !== $oldItem['activity_name']) {
                            $changes[] = "nama: '{$oldItem['activity_name']}' → '{$itemData['activity_name']}'";
                        }

                        // Cek perubahan is_required
                        $newIsRequired = $itemData['is_required'] ?? true;
                        if ($newIsRequired !== (bool) $oldItem['is_required']) {
                            $requiredText = $newIsRequired ? 'wajib' : 'opsional';
                            $oldRequiredText = $oldItem['is_required'] ? 'wajib' : 'opsional';
                            $changes[] = "status: {$oldRequiredText} → {$requiredText}";
                        }

                        // Cek perubahan urutan
                        $newOrder = $index + 1;
                        if ($newOrder !== $oldItem['order']) {
                            $changes[] = "urutan: posisi {$oldItem['order']} → posisi {$newOrder}";
                        }

                        // Update item
                        ChecklistItem::where('id', $itemId)->update([
                            'activity_name' => $itemData['activity_name'],
                            'order' => $newOrder,
                            'is_required' => $newIsRequired
                        ]);

                        // Log perubahan jika ada
                        if (!empty($changes)) {
                            $logEntries[] = [
                                'activity' => 'Update Checklist Item',
                                'detail_act' => "Mengubah item dalam checklist '{$checklistMaster->name}': " . implode(', ', $changes)
                            ];
                        }

                    } else {
                        // === CREATE NEW ITEM ===
                        ChecklistItem::create([
                            'checklist_master_id' => $checklistMaster->id,
                            'activity_name' => $itemData['activity_name'],
                            'order' => $index + 1,
                            'is_required' => $itemData['is_required'] ?? true
                        ]);

                        $requiredStatus = ($itemData['is_required'] ?? true) ? 'wajib' : 'opsional';
                        $logEntries[] = [
                            'activity' => 'Add Checklist Item',
                            'detail_act' => "Menambahkan item baru '{$itemData['activity_name']}' ({$requiredStatus}) ke checklist '{$checklistMaster->name}'"
                        ];
                    }
                }
            }

            // === SIMPAN SEMUA LOG ENTRIES ===
            $this->saveLogEntries($logEntries, $checklistMaster->id, $userForLogging);

            // Jika tidak ada perubahan spesifik, buat log umum
            if (empty($logEntries)) {
                $this->saveLogEntries([
                    [
                        'activity' => 'Update Master Checklist',
                        'detail_act' => "Memperbarui checklist '{$checklistMaster->name}' tanpa perubahan signifikan"
                    ]
                ], $checklistMaster->id, $userForLogging);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Master checklist berhasil diperbarui.',
                'data' => $checklistMaster->fresh()->load(['items', 'type'])
            ], 200);
        });
    }

    /**
     * Menghapus master checklist (soft delete).
     */
    public function destroy(ChecklistMaster $checklistMaster)
    {
        $checklistMaster->delete();
        return response()->json(null, 204);
    }

    /**
     * Helper: Menentukan user untuk logging dengan fallback logic
     */
    private function determineUserForLogging($request, $checklistMaster)
    {
        // Prioritas 1: User dari request
        if ($request->filled('id_karyawan')) {
            $user = User::where('karyawan_id', $request->id_karyawan)->first();
            if ($user)
                return $user;
        }

        // Prioritas 2: User yang membuat checklist
        $user = User::find($checklistMaster->created_by);
        if ($user)
            return $user;

        // Prioritas 3: User dummy system
        return (object) [
            'id' => 0,
            'karyawan_id' => 'SYSTEM',
            'name' => 'System User'
        ];
    }


    /**
     * Helper: Menyimpan multiple log entries
     */
    private function saveLogEntries($logEntries, $checklistMasterId, $userForLogging)
    {
        $logController = new Class_ChecklistLog();

        foreach ($logEntries as $entry) {
            try {
                $requestLog = [
                    'checklist_master_id' => $checklistMasterId,
                    'user_id' => $userForLogging->karyawan_id ?? $userForLogging->id,
                    'name' => $userForLogging->name,
                    'activity' => $entry['activity'],
                    'detail_act' => $entry['detail_act'],
                ];

                $logController->insert($requestLog);
            } catch (\Exception $e) {
                Log::warning('Gagal menyimpan activity log', [
                    'checklist_id' => $checklistMasterId,
                    'activity' => $entry['activity'],
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    public function getActivityLogs(ChecklistMaster $checklistMaster)
    {
        // Gunakan relasi jika sudah didefinisikan, atau query manual
        // Asumsi nama model log Anda adalah ChecklistLog
        $logs = ChecklistLog::where('checklist_master_id', $checklistMaster->id)
            ->latest() // Urutkan dari yang terbaru
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $logs
        ]);
    }
}