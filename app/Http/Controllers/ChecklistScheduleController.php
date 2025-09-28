<?php

namespace App\Http\Controllers;

use App\Models\ChecklistSchedule;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Class_ChecklistLog;
use App\Models\User;
use Illuminate\Support\Str;
use Exception;

class ChecklistScheduleController extends Controller
{
    public function index()
    {
        return ChecklistSchedule::with('master:id,name')->latest()->get();
    }

    /**
     * Menyimpan jadwal baru untuk sebuah Master Checklist dengan detailed logging.
     */

    public function store(Request $request)
    {
        $cleanedIdKaryawan = str_replace(['"', '\\'], '', $request->id_karyawan);
        $request->merge(['id_karyawan' => $cleanedIdKaryawan]);

        $validator = Validator::make($request->all(), [
            'checklist_master_id' => 'required|exists:checklist_masters,id',
            // 'schedule_name' => 'required|string|max:255', // Dihapus, karena akan digenerate otomatis
            'periode_type' => 'required|in:harian,mingguan,bulanan,tertentu',
            'schedule_details' => 'nullable|array',
            'end_date' => 'nullable|date_format:Y-m-d',
            'id_karyawan' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        try {
            // highlight-start
            $newSchedule = DB::transaction(function () use ($request) {
                // --- SINKRONISASI USER ---
                $user = $this->syncUserFromAPI($request->id_karyawan);

                // Ambil data master checklist untuk logging
                $master = ChecklistMaster::find($request->checklist_master_id);
                if (!$master) {
                    // Lemparkan exception jika master tidak ditemukan
                    throw new Exception("Master Checklist dengan ID {$request->checklist_master_id} tidak ditemukan.");
                }

                // 2. Membuat kode unik untuk nama jadwal
                $generatedName = 'JDL-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));

                $schedule = ChecklistSchedule::create([
                    'checklist_master_id' => $request->checklist_master_id,
                    'schedule_name' => $generatedName,
                    'periode_type' => $request->periode_type,
                    'schedule_details' => $request->schedule_details,
                    'created_by' => $user->id,
                    'end_date' => $request->end_date,
                ]);

                // === DETAILED LOGGING UNTUK CREATE SCHEDULE ===
                $this->logScheduleCreation($schedule, $master, $user, $request);

                // Kembalikan data mentah, bukan response
                return $schedule;
            });

            // Kirim response sukses di luar transaksi
            return response()->json([
                'status' => 'success',
                'message' => 'Jadwal checklist berhasil dibuat.',
                'data' => $newSchedule->load('master:id,name')
            ], 201);
            // highlight-end

        } catch (Exception $e) {
            // Tangkap semua exception (dari validasi manual atau proses DB)
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update jadwal dengan detailed comparison logging.
     */
    public function update(Request $request, ChecklistSchedule $schedule)
    {
        // Bersihkan id_karyawan jika ada
        if ($request->has('id_karyawan')) {
            $cleanedIdKaryawan = str_replace(['"', '\\'], '', $request->id_karyawan);
            $request->merge(['id_karyawan' => $cleanedIdKaryawan]);
        }

        $validator = Validator::make($request->all(), [
            'checklist_master_id' => 'sometimes|required|exists:checklist_masters,id',
            'periode_type' => 'sometimes|required|in:harian,mingguan,bulanan,tertentu',
            'schedule_details' => 'nullable|array',
            'end_date' => 'nullable|date_format:Y-m-d',
            'id_karyawan' => 'nullable|string|exists:users,karyawan_id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        return DB::transaction(function () use ($request, $schedule) {
            // Tentukan user untuk logging
            $userForLogging = $this->determineUserForLogging($request, $schedule);

            // Simpan data lama untuk comparison
            $oldData = [
                'checklist_master_id' => $schedule->checklist_master_id,
                'schedule_name' => $schedule->schedule_name,
                'periode_type' => $schedule->periode_type,
                'schedule_details' => $schedule->schedule_details,
                'end_date' => $schedule->end_date,
            ];

            // Ambil nama master lama dan baru untuk logging
            $oldMaster = $schedule->master;
            $newMaster = null;
            if ($request->filled('checklist_master_id') && $request->checklist_master_id !== $schedule->checklist_master_id) {
                $newMaster = ChecklistMaster::find($request->checklist_master_id);
            }

            // Update schedule
            $updateData = $request->only(['checklist_master_id', 'periode_type', 'schedule_details', 'end_date']);
            $schedule->update(array_filter($updateData, function ($value) {
                return $value !== null;
            }));

            // === DETAILED LOGGING UNTUK UPDATE ===
            $this->logScheduleUpdate($schedule, $oldData, $request->all(), $userForLogging, $oldMaster, $newMaster);

            return response()->json([
                'status' => 'success',
                'message' => 'Jadwal checklist berhasil diperbarui.',
                'data' => $schedule->fresh()->load('master:id,name')
            ], 200);
        });
    }

    /**
     * Menghapus data jadwal dengan logging.
     */
    public function destroy(Request $request, ChecklistSchedule $schedule)
    {
        // Tentukan user untuk logging
        $userForLogging = $this->determineUserForLogging($request, $schedule);
        $masterName = $schedule->master ? $schedule->master->name : 'Master Tidak Ditemukan';

        // Log penghapusan sebelum dihapus
        $this->logScheduleDeletion($schedule, $userForLogging, $masterName);

        $schedule->delete();
        return response()->json(null, 204);
    }

    public function show(ChecklistSchedule $schedule)
    {
        return $schedule->load('master');
    }

    /**
     * Mendapatkan jadwal yang jatuh tempo hari ini.
     */
    public function getTodaysSchedules()
    {
        $today = Carbon::now();

        // Ambil semua jadwal aktif yang belum melewati tanggal akhirnya
        $activeSchedules = ChecklistSchedule::with('master.type')
            ->where(function ($query) use ($today) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today->toDateString());
            })
            ->whereHas('master')
            ->get();

        // Filter jadwal yang jatuh tempo hari ini
        $dueTodaySchedules = $activeSchedules->filter(function ($schedule) use ($today) {
            switch ($schedule->periode_type) {
                case 'harian':
                    return true;
                case 'mingguan':
                    $dayName = strtolower($today->format('l'));
                    return in_array($dayName, $schedule->schedule_details ?? []);
                case 'bulanan':
                    return in_array($today->toDateString(), $schedule->schedule_details ?? []);
                case 'tertentu':
                    return in_array($today->toDateString(), $schedule->schedule_details ?? []);
                default:
                    return false;
            }
        });

        $dueTodayIds = $dueTodaySchedules->pluck('id');

        // Ambil data submission untuk jadwal yang jatuh tempo
        $todaySubmissions = ChecklistSubmission::whereIn('checklist_schedule_id', $dueTodayIds)
            ->whereDate('submission_date', $today->toDateString())
            ->get()
            ->keyBy('checklist_schedule_id');

        // Gabungkan data jadwal dengan status submission
        $result = $dueTodaySchedules->map(function ($schedule) use ($todaySubmissions) {
            $submission = $todaySubmissions->get($schedule->id);

            $schedule->setAttribute('today_submission', [
                'status' => $submission ? $submission->status : 'pending',
                'id' => $submission ? $submission->id : null,
            ]);
            return $schedule;
        });

        return $result->values();
    }

    /**
     * Helper: Sinkronisasi user dari API.
     */
    private function syncUserFromAPI($karyawanId)
    {
        try {
            $apiService = new API_Service();
            $karyawanDataResponse = $apiService->getDataKaryawan(['id_karyawan' => $karyawanId]);

            if (isset($karyawanDataResponse[0])) {
                $detailKaryawan = $karyawanDataResponse[0];
                $namaKaryawan = $detailKaryawan['name'] ?? 'User ' . $karyawanId;
                $emailKaryawan = $detailKaryawan['email'] ?? $karyawanId . '@internal.com';

                $user = User::updateOrCreate(
                    ['karyawan_id' => $karyawanId],
                    ['name' => $namaKaryawan, 'email' => $emailKaryawan]
                );

                if ($user->wasRecentlyCreated) {
                    $user->password = bcrypt(Str::random(10));
                    $user->save();
                }

                return $user;
            } else {
                throw new Exception('Respons API LokaHR tidak valid atau data karyawan tidak ditemukan.');
            }
        } catch (Exception $e) {
            Log::error('Gagal sinkronisasi data karyawan saat membuat jadwal.', [
                'id_karyawan' => $karyawanId,
                'error' => $e->getMessage()
            ]);

            return User::firstOrCreate(
                ['karyawan_id' => $karyawanId],
                [
                    'name' => 'User ' . $karyawanId,
                    'email' => $karyawanId . '@internal.com',
                    'password' => bcrypt(Str::random(10))
                ]
            );
        }
    }

    /**
     * Helper: Menentukan user untuk logging dengan fallback.
     */
    private function determineUserForLogging($request, $schedule)
    {
        // Prioritas 1: User dari request
        if ($request && $request->filled('id_karyawan')) {
            $user = User::where('karyawan_id', $request->id_karyawan)->first();
            if ($user)
                return $user;
        }

        // Prioritas 2: User yang membuat schedule
        $user = User::find($schedule->created_by);
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
     * Helper: Log pembuatan schedule dengan detail lengkap.
     */
    private function logScheduleCreation($schedule, $master, $user, $request)
    {
        try {
            $logController = new Class_ChecklistLog();

            // Format periode untuk logging
            $periodeText = $this->formatPeriodeForLog($schedule->periode_type, $schedule->schedule_details);

            // Format end date
            $endDateText = $schedule->end_date ?
                'sampai ' . Carbon::parse($schedule->end_date)->format('d/m/Y') :
                'tanpa batas waktu';

            $logController->insert([
                'checklist_master_id' => $schedule->checklist_master_id,
                'user_id' => $user->karyawan_id,
                'name' => $user->name,
                'activity' => 'Create Schedule',
                'detail_act' => "Membuat jadwal '{$schedule->schedule_name}' untuk checklist '{$master->name}' dengan periode {$periodeText} {$endDateText}"
            ]);

        } catch (Exception $e) {
            Log::warning('Gagal menulis log untuk pembuatan schedule.', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Helper: Log update schedule dengan comparison detail.
     */
    private function logScheduleUpdate($schedule, $oldData, $newData, $userForLogging, $oldMaster, $newMaster)
    {
        try {
            $logController = new Class_ChecklistLog();
            $logEntries = [];

            // 1. Log perubahan nama schedule
            if (isset($newData['schedule_name']) && $newData['schedule_name'] !== $oldData['schedule_name']) {
                $logEntries[] = [
                    'activity' => 'Update Schedule Name',
                    'detail_act' => "Mengubah nama jadwal dari '{$oldData['schedule_name']}' menjadi '{$newData['schedule_name']}'"
                ];
            }

            // 2. Log perubahan master checklist
            if (isset($newData['checklist_master_id']) && $newData['checklist_master_id'] !== $oldData['checklist_master_id']) {
                $oldMasterName = $oldMaster ? $oldMaster->name : 'Master Tidak Ditemukan';
                $newMasterName = $newMaster ? $newMaster->name : 'Master Tidak Ditemukan';

                $logEntries[] = [
                    'activity' => 'Update Schedule Master',
                    'detail_act' => "Mengubah target checklist jadwal '{$schedule->schedule_name}' dari '{$oldMasterName}' menjadi '{$newMasterName}'"
                ];
            }

            // 3. Log perubahan periode
            if (isset($newData['periode_type']) && $newData['periode_type'] !== $oldData['periode_type']) {
                $oldPeriodeText = $this->formatPeriodeForLog($oldData['periode_type'], $oldData['schedule_details']);
                $newPeriodeText = $this->formatPeriodeForLog($newData['periode_type'], $newData['schedule_details'] ?? []);

                $logEntries[] = [
                    'activity' => 'Update Schedule Period',
                    'detail_act' => "Mengubah periode jadwal '{$schedule->schedule_name}' dari {$oldPeriodeText} menjadi {$newPeriodeText}"
                ];
            } elseif (isset($newData['schedule_details']) && json_encode($newData['schedule_details']) !== json_encode($oldData['schedule_details'])) {
                // Detail periode berubah tanpa tipe periode berubah
                $newPeriodeText = $this->formatPeriodeForLog($schedule->periode_type, $newData['schedule_details']);

                $logEntries[] = [
                    'activity' => 'Update Schedule Details',
                    'detail_act' => "Mengubah detail periode jadwal '{$schedule->schedule_name}' menjadi {$newPeriodeText}"
                ];
            }

            // 4. Log perubahan end date
            if (array_key_exists('end_date', $newData) && $newData['end_date'] !== $oldData['end_date']) {
                $oldEndText = $oldData['end_date'] ?
                    Carbon::parse($oldData['end_date'])->format('d/m/Y') :
                    'tanpa batas waktu';
                $newEndText = $newData['end_date'] ?
                    Carbon::parse($newData['end_date'])->format('d/m/Y') :
                    'tanpa batas waktu';

                $logEntries[] = [
                    'activity' => 'Update Schedule End Date',
                    'detail_act' => "Mengubah batas waktu jadwal '{$schedule->schedule_name}' dari {$oldEndText} menjadi {$newEndText}"
                ];
            }

            // Simpan semua log entries
            foreach ($logEntries as $entry) {
                $logController->insert([
                    'checklist_master_id' => $schedule->checklist_master_id,
                    'user_id' => $userForLogging->karyawan_id ?? $userForLogging->id,
                    'name' => $userForLogging->name,
                    'activity' => $entry['activity'],
                    'detail_act' => $entry['detail_act'],
                ]);
            }

            // Jika tidak ada perubahan spesifik, buat log umum
            if (empty($logEntries)) {
                $logController->insert([
                    'checklist_master_id' => $schedule->checklist_master_id,
                    'user_id' => $userForLogging->karyawan_id ?? $userForLogging->id,
                    'name' => $userForLogging->name,
                    'activity' => 'Update Schedule',
                    'detail_act' => "Memperbarui jadwal '{$schedule->schedule_name}' tanpa perubahan signifikan"
                ]);
            }

        } catch (Exception $e) {
            Log::warning('Gagal menulis log untuk update schedule.', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Helper: Log penghapusan schedule.
     */
    private function logScheduleDeletion($schedule, $userForLogging, $masterName)
    {
        try {
            $logController = new Class_ChecklistLog();

            $periodeText = $this->formatPeriodeForLog($schedule->periode_type, $schedule->schedule_details);

            $logController->insert([
                'checklist_master_id' => $schedule->checklist_master_id,
                'user_id' => $userForLogging->karyawan_id ?? $userForLogging->id,
                'name' => $userForLogging->name,
                'activity' => 'Delete Schedule',
                'detail_act' => "Menghapus jadwal '{$schedule->schedule_name}' untuk checklist '{$masterName}' dengan periode {$periodeText}"
            ]);

        } catch (Exception $e) {
            Log::warning('Gagal menulis log untuk penghapusan schedule.', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Helper: Format periode untuk logging
     */
    private function formatPeriodeForLog($periodeType, $scheduleDetails)
    {
        switch ($periodeType) {
            case 'harian':
                return 'harian (setiap hari)';

            case 'mingguan':
                if (empty($scheduleDetails)) {
                    return 'mingguan (tidak ada hari yang dipilih)';
                }
                $dayMap = [
                    'monday' => 'Senin',
                    'tuesday' => 'Selasa',
                    'wednesday' => 'Rabu',
                    'thursday' => 'Kamis',
                    'friday' => 'Jumat',
                    'saturday' => 'Sabtu',
                    'sunday' => 'Minggu'
                ];
                $days = array_map(function ($day) use ($dayMap) {
                    return $dayMap[$day] ?? $day;
                }, $scheduleDetails);
                return 'mingguan (setiap ' . implode(', ', $days) . ')';

            case 'bulanan':
                if (empty($scheduleDetails)) {
                    return 'bulanan (tidak ada tanggal yang dipilih)';
                }
                $dates = array_map(function ($date) {
                    return Carbon::parse($date)->format('d/m');
                }, $scheduleDetails);
                return 'bulanan (tanggal ' . implode(', ', $dates) . ')';

            case 'tertentu':
                if (empty($scheduleDetails)) {
                    return 'tanggal tertentu (tidak ada tanggal yang dipilih)';
                }
                $dates = array_map(function ($date) {
                    return Carbon::parse($date)->format('d/m/Y');
                }, $scheduleDetails);
                return 'tanggal tertentu (' . implode(', ', $dates) . ')';

            default:
                return $periodeType;
        }
    }
}