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
            // $karyawanData = $apiService->getDataKaryawan(['id_karyawan' => $request->id_karyawan]);
            $response = $apiService->getDataKaryawan(['id_karyawan' => $request->id_karyawan]);

            // Validasi response dari API
            if (!$response || $response['status'] !== 'success' || !isset($response['data'])) { // <-- Validasi lebih ketat
                Log::warning('API getDataKaryawan tidak mengembalikan data yang valid.', ['response' => $response]);
                // Kita tidak langsung return error, tapi biarkan fallback di bawah yang menanganinya
                $karyawanData = null;
            } else {
                $karyawanData = $response['data']; // <-- AMBIL DATA DARI DALAM KEY 'data'
            }

            // Debug: Log response untuk melihat struktur data
            Log::info('Karyawan Data Response:', ['data' => $response]);

            // Langkah 1: Cari user berdasarkan karyawan_id
            $user = User::where('karyawan_id', $request->id_karyawan)->first();

            // Langkah 2: Jika user TIDAK DITEMUKAN, maka buat baru
            if (!$user) {
                // Ekstrak data dengan fallback values
                // Sekarang, $karyawanData adalah array yang benar
                $userName = $karyawanData['nama'] ?? $karyawanData['name'] ?? 'User ' . $request->id_karyawan;
                $userEmail = $karyawanData['email_karyawan'] ?? $karyawanData['email'] ?? $request->id_karyawan . '@internal.com';

                $user = User::create([
                    'karyawan_id' => $request->id_karyawan,
                    'name' => $userName,
                    'email' => $userEmail,
                    'password' => bcrypt(Str::random(10))
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error getting karyawan data:', [
                'id_karyawan' => $request->id_karyawan,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback: buat user dengan data minimal
            $user = User::where('karyawan_id', $request->id_karyawan)->first();

            if (!$user) {
                $user = User::create([
                    'karyawan_id' => $request->id_karyawan,
                    'name' => 'User ' . $request->id_karyawan,
                    'email' => $request->id_karyawan . '@internal.com',
                    'password' => bcrypt(Str::random(10))
                ]);
            }
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