<?php

namespace App\Http\Controllers;

use App\Models\ChecklistSchedule;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistSubmissionDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ChecklistSubmissionController extends Controller
{
    /**
     * Menampilkan daftar checklist submission.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        $query = ChecklistSubmission::with([
            'schedule.master:id,name,type',
            'user:id,name'
        ]);

        if ($request->filled('date')) {
            $targetDate = Carbon::parse($request->input('date'));
            $query->whereDate('submission_date', $targetDate->toDateString());
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest('submission_date')->get()
        ]);
    }

    /**
     * Menampilkan detail item dari satu submission.
     */
    public function show($submissionId)
    {
        $submission = ChecklistSubmission::with([
            'schedule.master:id,name',
            'details.item:id,activity_name,is_required'
        ])->findOrFail($submissionId);

        return response()->json([
            'status' => 'success',
            'data' => $submission
        ]);
    }

    /**
     * Menyimpan aksi ceklis dari user.
     */
    public function storeCheck(Request $request, $submissionDetailId)
    {
        // Bersihkan id_karyawan agar tidak ada karakter aneh
        $cleanedIdKaryawan = str_replace(['"', '\\'], '', $request->id_karyawan);
        $request->merge(['id_karyawan' => $cleanedIdKaryawan]);

        $validator = Validator::make($request->all(), [
            'is_checked' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
            'id_karyawan' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($request, $submissionDetailId) {
            $detail = ChecklistSubmissionDetail::with('item')->findOrFail($submissionDetailId);
            $submission = $detail->submission;

            // --- Sinkronisasi User (mengacu ke ChecklistMasterController) ---
            try {
                $apiService = new API_Service();
                $karyawanDataResponse = $apiService->getDataKaryawan(['id_karyawan' => $request->id_karyawan]);

                if (!empty($karyawanDataResponse[0])) {
                    $detailKaryawan = $karyawanDataResponse[0];
                    $namaKaryawan = $detailKaryawan['name'] ?? 'User ' . $request->id_karyawan;
                    $emailKaryawan = $detailKaryawan['email'] ?? $request->id_karyawan . '@internal.com';

                    $user = \App\Models\User::updateOrCreate(
                        ['karyawan_id' => $request->id_karyawan],
                        ['name' => $namaKaryawan, 'email' => $emailKaryawan]
                    );

                    if ($user->wasRecentlyCreated) {
                        $user->update(['password' => bcrypt(\Illuminate\Support\Str::random(10))]);
                    }
                } else {
                    throw new Exception('API LokaHR: Data karyawan tidak ditemukan.');
                }
            } catch (Exception $e) {
                Log::error('Gagal sinkronisasi user di storeCheck', [
                    'id_karyawan' => $request->id_karyawan,
                    'error' => $e->getMessage()
                ]);

                // fallback user generik
                $user = \App\Models\User::firstOrCreate(
                    ['karyawan_id' => $request->id_karyawan],
                    [
                        'name' => 'User ' . $request->id_karyawan,
                        'email' => $request->id_karyawan . '@internal.com',
                        'password' => bcrypt(\Illuminate\Support\Str::random(10))
                    ]
                );
            }

            // Update detail checklist
            $detail->update([
                'is_checked' => $request->is_checked,
                'notes' => $request->notes
            ]);

            // Update submission utama
            $submission->update(['submitted_by' => $user->id]);
            $this->updateSubmissionStatus($submission);

            // --- Tambahkan Log Activity ---
            try {
                $logController = new Class_ChecklistLog();
                $logController->insert([
                    'checklist_master_id' => $submission->schedule->checklist_master_id,
                    'user_id' => $user->karyawan_id,
                    'name' => $user->name,
                    'activity' => $request->is_checked ? 'Check Item' : 'Uncheck Item',
                    'detail_act' => "User '{$user->name}' mengubah status item '{$detail->item->activity_name}' menjadi " .
                        ($request->is_checked ? 'selesai' : 'belum selesai') .
                        ($request->notes ? " dengan catatan: '{$request->notes}'" : ''),
                ]);
            } catch (Exception $e) {
                Log::warning('Gagal menulis log activity.', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Checklist item berhasil diperbarui.',
                'data' => $detail
            ]);
        });
    }

    /**
     * Mencari submission hari ini untuk jadwal tertentu.
     * Jika tidak ada, buat baru secara on-demand.
     */
    public function startOrGetTodaySubmission(ChecklistSchedule $schedule)
    {

        // Langkah 1: Muat relasi master secara eksplisit.
        $schedule->load('master');

        // Langkah 2: Lakukan pengecekan yang ketat.
        // Jika master tidak ada (karena soft-delete), lemparkan error yang jelas.
        if (!$schedule->master) {
            throw new Exception("Gagal memulai: Master Checklist untuk jadwal '{$schedule->schedule_name}' telah dihapus.");
        }

        // Muat relasi items setelah kita yakin master-nya ada.
        $schedule->master->load('items');

        // Jika master ada tapi tidak punya item, lemparkan error yang jelas.
        if ($schedule->master->items->isEmpty()) {
            throw new Exception("Gagal memulai: Master Checklist '{$schedule->master->name}' tidak memiliki satupun activity item.");
        }

        $today = Carbon::now()->toDateString();

        $submission = ChecklistSubmission::firstOrCreate(
            [
                'checklist_schedule_id' => $schedule->id,
                'submission_date' => $today,
            ],
            [
                'submitted_by' => $schedule->created_by,
                'status' => 'pending',
            ]
        );

        if ($submission->wasRecentlyCreated) {
            // Karena kita sudah memuat relasi di atas, loop ini sekarang dijamin aman.
            foreach ($schedule->master->items as $item) {
                ChecklistSubmissionDetail::create([
                    'submission_id' => $submission->id,
                    'item_id' => $item->id,
                    'is_checked' => false,
                ]);
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $submission
        ]);
    }



    /**
     * Update status submission.
     */
    private function updateSubmissionStatus(ChecklistSubmission $submission)
    {
        $totalItems = $submission->details()->count();
        $checkedItems = $submission->details()->where('is_checked', true)->count();
        $newStatus = 'pending';
        if ($checkedItems > 0 && $checkedItems < $totalItems) {
            $newStatus = 'incomplete';
        } elseif ($checkedItems === $totalItems) {
            $newStatus = 'completed';
        }
        $submission->update(['status' => $newStatus]);
    }
}