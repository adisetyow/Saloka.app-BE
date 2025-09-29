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
            'periode_type' => 'required|in:harian,mingguan,bulanan,tertentu',
            'schedule_details' => 'nullable|array',
            'end_date' => 'nullable|date_format:Y-m-d',
            'id_karyawan' => 'required|string',
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
                throw new Exception('Respons API LokaHR tidak valid atau data karyawan tidak ditemukan.');
            }
        } catch (Exception $e) {
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
            // Ambil data master checklist untuk logging
            $master = ChecklistMaster::find($request->checklist_master_id);
            if (!$master) {
                throw new Exception("Master Checklist dengan ID {$request->checklist_master_id} tidak ditemukan.");
            }

            // Membuat kode unik untuk nama jadwal
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
            $periodeText = $this->formatPeriodeForLog($schedule->periode_type, $schedule->schedule_details);
            $endDateText = $schedule->end_date ?
                'sampai ' . Carbon::parse($schedule->end_date)->format('d/m/Y') :
                'tanpa batas waktu';

            // Log create schedule
            $requestLog = [
                'checklist_master_id' => $schedule->checklist_master_id,
                'user_id' => $user->karyawan_id,
                'name' => $user->name,
                'activity' => 'Create Schedule',
                'detail_act' => "Membuat jadwal '{$schedule->schedule_name}' untuk checklist '{$master->name}' dengan periode {$periodeText} {$endDateText}"
            ];

            $logController = new Class_ChecklistLog();
            $logController->insert($requestLog);

            return response()->json([
                'status' => 'success',
                'message' => 'Jadwal checklist berhasil dibuat.',
                'data' => $schedule->load('master:id,name')
            ], 201);
        });
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

        return DB::transaction(function () use ($request, $schedule, $userForLogging, $oldData, $oldMaster, $newMaster) {
            $logEntries = [];

            // Update schedule
            $updateData = $request->only(['checklist_master_id', 'periode_type', 'schedule_details', 'end_date']);
            if (!empty($updateData)) {
                $schedule->update(array_filter($updateData, function ($value) {
                    return $value !== null;
                }));

                // === DETAILED LOGGING UNTUK UPDATE ===

                // 1. Log perubahan master checklist
                if (isset($updateData['checklist_master_id']) && $updateData['checklist_master_id'] !== $oldData['checklist_master_id']) {
                    $oldMasterName = $oldMaster ? $oldMaster->name : 'Master Tidak Ditemukan';
                    $newMasterName = $newMaster ? $newMaster->name : 'Master Tidak Ditemukan';

                    $logEntries[] = [
                        'activity' => 'Update Schedule Master',
                        'detail_act' => "Mengubah target checklist jadwal '{$schedule->schedule_name}' dari '{$oldMasterName}' menjadi '{$newMasterName}'"
                    ];
                }

                // 2. Log perubahan periode
                if (isset($updateData['periode_type']) && $updateData['periode_type'] !== $oldData['periode_type']) {
                    $oldPeriodeText = $this->formatPeriodeForLog($oldData['periode_type'], $oldData['schedule_details']);
                    $newPeriodeText = $this->formatPeriodeForLog($updateData['periode_type'], $updateData['schedule_details'] ?? []);

                    $logEntries[] = [
                        'activity' => 'Update Schedule Period',
                        'detail_act' => "Mengubah periode jadwal '{$schedule->schedule_name}' dari {$oldPeriodeText} menjadi {$newPeriodeText}"
                    ];
                } elseif (isset($updateData['schedule_details']) && json_encode($updateData['schedule_details']) !== json_encode($oldData['schedule_details'])) {
                    // Detail periode berubah tanpa tipe periode berubah
                    $newPeriodeText = $this->formatPeriodeForLog($schedule->periode_type, $updateData['schedule_details']);

                    $logEntries[] = [
                        'activity' => 'Update Schedule Details',
                        'detail_act' => "Mengubah detail periode jadwal '{$schedule->schedule_name}' menjadi {$newPeriodeText}"
                    ];
                }

                // 3. Log perubahan end date
                if (array_key_exists('end_date', $updateData) && $updateData['end_date'] !== $oldData['end_date']) {
                    $oldEndText = $oldData['end_date'] ?
                        Carbon::parse($oldData['end_date'])->format('d/m/Y') :
                        'tanpa batas waktu';
                    $newEndText = $updateData['end_date'] ?
                        Carbon::parse($updateData['end_date'])->format('d/m/Y') :
                        'tanpa batas waktu';

                    $logEntries[] = [
                        'activity' => 'Update Schedule End Date',
                        'detail_act' => "Mengubah batas waktu jadwal '{$schedule->schedule_name}' dari {$oldEndText} menjadi {$newEndText}"
                    ];
                }
            }

            // === SIMPAN SEMUA LOG ENTRIES ===
            $this->saveLogEntries($logEntries, $schedule->checklist_master_id, $userForLogging);

            // Jika tidak ada perubahan spesifik, buat log umum
            if (empty($logEntries)) {
                $this->saveLogEntries([
                    [
                        'activity' => 'Update Schedule',
                        'detail_act' => "Memperbarui jadwal '{$schedule->schedule_name}' tanpa perubahan signifikan"
                    ]
                ], $schedule->checklist_master_id, $userForLogging);
            }

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
    public function destroy(ChecklistSchedule $schedule)
    {
        // Tentukan user untuk logging menggunakan helper request()
        // Anda tidak perlu mengirim data id_karyawan saat delete
        $userForLogging = $this->determineUserForLogging(request(), $schedule);
        $masterName = $schedule->master ? $schedule->master->name : 'Master Tidak Ditemukan';

        // Log penghapusan sebelum dihapus
        $periodeText = $this->formatPeriodeForLog($schedule->periode_type, $schedule->schedule_details);

        $this->saveLogEntries([
            [
                'activity' => 'Delete Schedule',
                'detail_act' => "Menghapus jadwal '{$schedule->schedule_name}' untuk checklist '{$masterName}' dengan periode {$periodeText}"
            ]
        ], $schedule->checklist_master_id, $userForLogging);

        $schedule->delete();
        return response()->json(null, 204);
    }

    public function show(ChecklistSchedule $schedule)
    {
        return $schedule->load('master', 'creator');
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
     * Helper: Menentukan user untuk logging dengan fallback logic
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
            } catch (Exception $e) {
                Log::warning('Gagal menyimpan activity log', [
                    'checklist_id' => $checklistMasterId,
                    'activity' => $entry['activity'],
                    'error' => $e->getMessage()
                ]);
            }
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

    public function getActivityLogs(ChecklistSchedule $schedule)
    {
        try {
            $logs = \App\Models\ChecklistLog::where('checklist_master_id', $schedule->checklist_master_id)
                ->where('detail_act', 'like', "%{$schedule->schedule_name}%")
                ->latest()
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $logs
            ]);

        } catch (Exception $e) {
            Log::error("Gagal mengambil log untuk schedule ID: {$schedule->id}", ['error' => $e->getMessage()]);
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan saat mengambil data log.'
            ], 500);
        }
    }
}