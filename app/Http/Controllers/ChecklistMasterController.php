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

            // Cek jika respons adalah array dan memiliki elemen pertama (data user)
            if (isset($dataKaryawanResponse[0])) {
                $detailKaryawan = $dataKaryawanResponse[0];

                // Ambil nama dan email dari API, dengan fallback jika tidak ada
                $namaKaryawan = $detailKaryawan['name'] ?? 'User ' . $request->id_karyawan;
                $emailKaryawan = $detailKaryawan['email'] ?? $request->id_karyawan . '@internal.com';

                // Cari user yang ada, atau buat baru jika tidak ada
                $user = User::updateOrCreate(
                    ['karyawan_id' => $request->id_karyawan],
                    [
                        'name' => $namaKaryawan,
                        'email' => $emailKaryawan,
                    ]
                );

                // Jika user baru dibuat, berikan password acak
                if ($user->wasRecentlyCreated) {
                    $user->password = bcrypt(Str::random(10));
                    $user->save();
                }

            } else {
                // Jika API tidak mengembalikan data yang valid, lempar exception
                throw new \Exception('Respons API LokaHR tidak valid atau data karyawan tidak ditemukan.');
            }

        } catch (\Exception $e) {
            // Log error yang lebih detail untuk membantu debugging
            Log::error('Gagal sinkronisasi data karyawan dari API LokaHR.', [
                'id_karyawan' => $request->id_karyawan,
                'error' => $e->getMessage(),
                'api_response' => $dataKaryawanResponse ?? 'Tidak ada respons'
            ]);

            // Fallback: Buat user dengan nama generik HANYA JIKA BENAR-BENAR GAGAL
            $user = User::firstOrCreate(
                ['karyawan_id' => $request->id_karyawan],
                [
                    'name' => 'User ' . $request->id_karyawan,
                    'email' => $request->id_karyawan . '@internal.com',
                    'password' => bcrypt(Str::random(10))
                ]
            );
        }
        // --- AKHIR LOGIKA SINKRONISASI ---

        return DB::transaction(function () use ($request, $user) {
            $master = ChecklistMaster::create([
                'checklist_id' => 'CK-' . strtoupper(Str::random(8)),
                'name' => $request->name,
                'checklist_type_id' => $request->checklist_type_id,
                'created_by' => $user->id,
            ]);

            foreach ($request->items as $index => $itemData) {
                ChecklistItem::create([
                    'checklist_master_id' => $master->id,
                    'activity_name' => $itemData['activity_name'],
                    'order' => $index + 1,
                    'is_required' => $itemData['is_required'] ?? true,
                ]);
            }
            $requestLog = [
                'checklist_master_id' => $master->id,
                'user_id' => $user->karyawan_id,
                'name' => $user->name,
                'activity' => 'Create Master Checklist',
                'detail_act' => 'Membuat master checklist baru dengan nama: ' . $master->name,
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
        return $checklistMaster->load('items');
    }

    /**
     * Mengupdate data master checklist.

     */
    public function update(Request $request, ChecklistMaster $checklistMaster)
    {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'checklist_type_id' => 'sometimes|required|integer|exists:checklist_types,id',
            'items' => 'sometimes|required|array|min:1',
            'items.*.id' => 'nullable|integer|exists:checklist_items,id',
            'items.*.activity_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($request, $checklistMaster) {
            $checklistMaster->update($request->only('name', 'type'));

            if ($request->has('items')) {
                $incomingItems = $request->input('items', []);
                $incomingItemIds = array_filter(array_column($incomingItems, 'id'));

                ChecklistItem::where('checklist_master_id', $checklistMaster->id)
                    ->whereNotIn('id', $incomingItemIds)
                    ->delete();

                foreach ($incomingItems as $index => $itemData) {
                    ChecklistItem::updateOrCreate(
                        [

                            'id' => $itemData['id'] ?? null,
                            'checklist_master_id' => $checklistMaster->id
                        ],
                        [

                            'activity_name' => $itemData['activity_name'],
                            'order' => $index + 1,
                            'is_required' => $itemData['is_required'] ?? true
                        ]
                    );
                }
            }

            return $checklistMaster->load('items');
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
}