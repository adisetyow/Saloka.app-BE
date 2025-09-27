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

        try {
            $query = ChecklistSubmission::with([
                'schedule.master:id,name,checklist_type_id',
                'schedule.master.type:id,name',
                'user:id,name,karyawan_id'
            ]);

            if ($request->filled('date')) {
                $targetDate = Carbon::parse($request->input('date'));
                $query->whereDate('submission_date', $targetDate->toDateString());
            }

            $submissions = $query->latest('submission_date')->get();

            return response()->json([
                'status' => 'success',
                'data' => $submissions
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching submissions', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat daftar submission.'
            ], 500);
        }
    }

    /**
     * Menampilkan detail item dari satu submission.
     */
    public function show($submissionId)
    {
        try {
            // Validasi ID submission
            if (!is_numeric($submissionId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'ID submission tidak valid.'
                ], 400);
            }

            $submission = ChecklistSubmission::with([
                'schedule.master:id,name,checklist_type_id',
                'schedule.master.type:id,name',
                'details.item:id,activity_name,is_required,order',
                'user:id,name,karyawan_id'
            ])->find($submissionId);

            if (!$submission) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Submission tidak ditemukan.'
                ], 404);
            }

            // Periksa apakah master masih ada
            if (!$submission->schedule || !$submission->schedule->master) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Master checklist untuk submission ini telah dihapus.'
                ], 404);
            }

            // Sort details berdasarkan order
            if ($submission->details) {
                $submission->details = $submission->details->sortBy('item.order');
            }

            return response()->json([
                'status' => 'success',
                'data' => $submission
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching submission detail', [
                'submission_id' => $submissionId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat detail checklist.'
            ], 500);
        }
    }

    /**
     * Menyimpan aksi ceklis dari user dengan detailed logging.
     */
    public function storeCheck(Request $request, $submissionDetailId)
    {
        // Bersihkan id_karyawan agar tidak ada karakter aneh
        if ($request->has('id_karyawan')) {
            $cleanedIdKaryawan = str_replace(['"', '\\'], '', $request->id_karyawan);
            $request->merge(['id_karyawan' => $cleanedIdKaryawan]);
        }

        $validator = Validator::make($request->all(), [
            'is_checked' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
            'id_karyawan' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        try {
            return DB::transaction(function () use ($request, $submissionDetailId) {
                // Validasi submission detail ID
                if (!is_numeric($submissionDetailId)) {
                    throw new Exception('ID submission detail tidak valid.');
                }

                $detail = ChecklistSubmissionDetail::with([
                    'item',
                    'submission.schedule.master',
                    'submission.user'
                ])->find($submissionDetailId);

                if (!$detail) {
                    throw new Exception('Submission detail tidak ditemukan.');
                }

                $submission = $detail->submission;
                $schedule = $submission->schedule;
                $master = $schedule->master;

                // Periksa apakah master masih ada
                if (!$master) {
                    throw new Exception('Master checklist untuk item ini telah dihapus.');
                }

                // Simpan status lama untuk comparison
                $oldStatus = [
                    'is_checked' => $detail->is_checked,
                    'notes' => $detail->notes,
                    'submission_status' => $submission->status
                ];

                // --- Sinkronisasi User ---
                $user = $this->syncUserFromAPI($request->id_karyawan);

                // Update detail checklist
                $detail->update([
                    'is_checked' => $request->is_checked,
                    'notes' => $request->notes
                ]);

                // Update submission utama
                $submission->update(['submitted_by' => $user->id]);
                $this->updateSubmissionStatus($submission);

                // Refresh untuk mendapatkan status terbaru
                $submission->refresh();

                // === DETAILED ACTIVITY LOGGING ===
                $this->logChecklistItemActivity($detail, $oldStatus, $request, $user, $master, $submission);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Checklist item berhasil diperbarui.',
                    'data' => $detail->fresh(['item'])
                ]);
            });

        } catch (Exception $e) {
            Log::error('Error in storeCheck', [
                'submission_detail_id' => $submissionDetailId,
                'request' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mencari submission hari ini untuk jadwal tertentu.
     * Jika tidak ada, buat baru secara on-demand dengan logging.
     */
    public function startOrGetTodaySubmission($scheduleId, ?Request $request = null)
    {
        try {
            // Validasi schedule ID
            if (!is_numeric($scheduleId)) {
                throw new Exception('ID jadwal tidak valid.');
            }

            $schedule = ChecklistSchedule::with(['master.items'])->find($scheduleId);

            if (!$schedule) {
                throw new Exception('Jadwal checklist tidak ditemukan.');
            }

            // Langkah 2: Pengecekan yang ketat
            if (!$schedule->master) {
                throw new Exception("Gagal memulai: Master Checklist untuk jadwal '{$schedule->schedule_name}' telah dihapus.");
            }

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
                // Buat detail items
                $createdItemNames = [];
                foreach ($schedule->master->items->sortBy('order') as $item) {
                    ChecklistSubmissionDetail::create([
                        'submission_id' => $submission->id,
                        'item_id' => $item->id,
                        'is_checked' => false,
                    ]);
                    $createdItemNames[] = $item->activity_name;
                }

                // Log pembuatan submission baru
                $this->logSubmissionStart($submission, $schedule, $createdItemNames, $request);
            }

            // Load relasi lengkap untuk response
            $submission->load([
                'details.item:id,activity_name,is_required,order',
                'schedule.master:id,name'
            ]);

            // Sort details berdasarkan order item
            if ($submission->details) {
                $submission->details = $submission->details->sortBy('item.order');
            }

            return response()->json([
                'status' => 'success',
                'data' => $submission
            ]);

        } catch (Exception $e) {
            Log::error('Error in startOrGetTodaySubmission', [
                'schedule_id' => $scheduleId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update status submission dengan logging perubahan status.
     */
    private function updateSubmissionStatus(ChecklistSubmission $submission)
    {
        $oldStatus = $submission->status;

        $totalItems = $submission->details()->count();
        $checkedItems = $submission->details()->where('is_checked', true)->count();

        $newStatus = 'pending';
        if ($checkedItems > 0 && $checkedItems < $totalItems) {
            $newStatus = 'incomplete';
        } elseif ($checkedItems === $totalItems && $totalItems > 0) {
            $newStatus = 'completed';
        }

        $submission->update(['status' => $newStatus]);

        // Log perubahan status jika berbeda
        if ($oldStatus !== $newStatus) {
            $this->logSubmissionStatusChange($submission, $oldStatus, $newStatus, $checkedItems, $totalItems);
        }
    }

    /**
     * Helper: Sinkronisasi user dari API dengan error handling.
     */
    private function syncUserFromAPI($idKaryawan)
    {
        try {
            $apiService = new API_Service();
            $karyawanDataResponse = $apiService->getDataKaryawan(['id_karyawan' => $idKaryawan]);

            if (!empty($karyawanDataResponse[0])) {
                $detailKaryawan = $karyawanDataResponse[0];
                $namaKaryawan = $detailKaryawan['name'] ?? 'User ' . $idKaryawan;
                $emailKaryawan = $detailKaryawan['email'] ?? $idKaryawan . '@internal.com';

                $user = \App\Models\User::updateOrCreate(
                    ['karyawan_id' => $idKaryawan],
                    ['name' => $namaKaryawan, 'email' => $emailKaryawan]
                );

                if ($user->wasRecentlyCreated) {
                    $user->update(['password' => bcrypt(\Illuminate\Support\Str::random(10))]);
                }

                return $user;
            } else {
                throw new Exception('API LokaHR: Data karyawan tidak ditemukan.');
            }
        } catch (Exception $e) {
            Log::error('Gagal sinkronisasi user di storeCheck', [
                'id_karyawan' => $idKaryawan,
                'error' => $e->getMessage()
            ]);

            // Fallback user generik
            return \App\Models\User::firstOrCreate(
                ['karyawan_id' => $idKaryawan],
                [
                    'name' => 'User ' . $idKaryawan,
                    'email' => $idKaryawan . '@internal.com',
                    'password' => bcrypt(\Illuminate\Support\Str::random(10))
                ]
            );
        }
    }

    /**
     * Helper: Log aktivitas checklist item dengan detail lengkap.
     */
    private function logChecklistItemActivity($detail, $oldStatus, $request, $user, $master, $submission)
    {
        try {
            $logController = new Class_ChecklistLog();
            $itemName = $detail->item->activity_name;
            $masterName = $master->name;
            $submissionDate = Carbon::parse($submission->submission_date)->format('d/m/Y');

            $logEntries = [];

            // 1. Log perubahan status check/uncheck
            if ($oldStatus['is_checked'] !== $request->is_checked) {
                $action = $request->is_checked ? 'Check' : 'Uncheck';
                $statusText = $request->is_checked ? 'selesai' : 'belum selesai';

                $logEntries[] = [
                    'activity' => $action . ' Checklist Item',
                    'detail_act' => "Mengubah status item '{$itemName}' menjadi {$statusText} pada checklist '{$masterName}' tanggal {$submissionDate}"
                ];
            }

            // 2. Log perubahan notes
            if ($oldStatus['notes'] !== $request->notes) {
                if (empty($oldStatus['notes']) && !empty($request->notes)) {
                    // Menambahkan notes baru
                    $logEntries[] = [
                        'activity' => 'Add Item Note',
                        'detail_act' => "Menambahkan catatan '{$request->notes}' pada item '{$itemName}' checklist '{$masterName}' tanggal {$submissionDate}"
                    ];
                } elseif (!empty($oldStatus['notes']) && empty($request->notes)) {
                    // Menghapus notes
                    $logEntries[] = [
                        'activity' => 'Remove Item Note',
                        'detail_act' => "Menghapus catatan pada item '{$itemName}' checklist '{$masterName}' tanggal {$submissionDate}"
                    ];
                } elseif ($oldStatus['notes'] !== $request->notes) {
                    // Mengubah notes
                    $logEntries[] = [
                        'activity' => 'Update Item Note',
                        'detail_act' => "Mengubah catatan item '{$itemName}' dari '{$oldStatus['notes']}' menjadi '{$request->notes}' pada checklist '{$masterName}' tanggal {$submissionDate}"
                    ];
                }
            }

            // 3. Log perubahan status submission (jika ada)
            if ($oldStatus['submission_status'] !== $submission->status) {
                $statusMap = [
                    'pending' => 'pending',
                    'incomplete' => 'sebagian selesai',
                    'completed' => 'selesai'
                ];

                $oldStatusText = $statusMap[$oldStatus['submission_status']] ?? $oldStatus['submission_status'];
                $newStatusText = $statusMap[$submission->status] ?? $submission->status;

                $logEntries[] = [
                    'activity' => 'Update Submission Status',
                    'detail_act' => "Status submission checklist '{$masterName}' tanggal {$submissionDate} berubah dari {$oldStatusText} menjadi {$newStatusText}"
                ];
            }

            // Simpan semua log entries
            foreach ($logEntries as $entry) {
                $logController->insert([
                    'checklist_master_id' => $master->id,
                    'user_id' => $user->karyawan_id,
                    'name' => $user->name,
                    'activity' => $entry['activity'],
                    'detail_act' => $entry['detail_act'],
                ]);
            }

        } catch (Exception $e) {
            Log::warning('Gagal menulis log activity untuk checklist item.', [
                'submission_detail_id' => $detail->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Helper: Log pembuatan submission baru.
     */
    private function logSubmissionStart($submission, $schedule, $createdItemNames, $request)
    {
        try {
            $logController = new Class_ChecklistLog();
            $submissionDate = Carbon::parse($submission->submission_date)->format('d/m/Y');
            $masterName = $schedule->master->name;
            $itemCount = count($createdItemNames);

            // Tentukan user yang memulai submission
            $userId = 'SYSTEM';
            $userName = 'System';

            if ($request && $request->filled('id_karyawan')) {
                $user = $this->syncUserFromAPI($request->id_karyawan);
                $userId = $user->karyawan_id;
                $userName = $user->name;
            }

            $logController->insert([
                'checklist_master_id' => $schedule->master->id,
                'user_id' => $userId,
                'name' => $userName,
                'activity' => 'Start Submission',
                'detail_act' => "Memulai submission checklist '{$masterName}' tanggal {$submissionDate} dengan {$itemCount} item: [" . implode(', ', $createdItemNames) . "]"
            ]);

        } catch (Exception $e) {
            Log::warning('Gagal menulis log untuk submission start.', [
                'submission_id' => $submission->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Helper: Log perubahan status submission.
     */
    private function logSubmissionStatusChange($submission, $oldStatus, $newStatus, $checkedItems, $totalItems)
    {
        try {
            $logController = new Class_ChecklistLog();
            $schedule = $submission->schedule;
            $masterName = $schedule->master->name;
            $submissionDate = Carbon::parse($submission->submission_date)->format('d/m/Y');

            $statusMap = [
                'pending' => 'pending',
                'incomplete' => 'sebagian selesai',
                'completed' => 'selesai'
            ];

            $oldStatusText = $statusMap[$oldStatus] ?? $oldStatus;
            $newStatusText = $statusMap[$newStatus] ?? $newStatus;
            $progress = "({$checkedItems}/{$totalItems} item)";

            // Gunakan submitted_by dari submission atau system sebagai fallback
            $user = $submission->user;
            $userId = $user ? $user->karyawan_id : 'SYSTEM';
            $userName = $user ? $user->name : 'System';

            $logController->insert([
                'checklist_master_id' => $schedule->master->id,
                'user_id' => $userId,
                'name' => $userName,
                'activity' => 'Auto Update Submission Status',
                'detail_act' => "Status submission checklist '{$masterName}' tanggal {$submissionDate} otomatis berubah dari {$oldStatusText} menjadi {$newStatusText} {$progress}"
            ]);

        } catch (Exception $e) {
            Log::warning('Gagal menulis log untuk perubahan status submission.', [
                'submission_id' => $submission->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'error' => $e->getMessage()
            ]);
        }
    }
}