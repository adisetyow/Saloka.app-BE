<?php

namespace App\Console\Commands;

use App\Models\ChecklistSchedule;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistSubmissionDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateDailyChecklists extends Command
{
    /**
     * Nama dan signature dari command.
     */
    protected $signature = 'checklists:generate';

    /**
     * Deskripsi dari command.
     */
    protected $description = 'Generate daily checklist submissions based on active schedules';

    /**
     * Eksekusi logika command.
     */
    public function handle()
    {

        $this->info('Starting to generate daily checklists...');
        Log::info('Scheduler Job: Starting GenerateDailyChecklists.');

        $today = Carbon::now();

        $schedules = ChecklistSchedule::with('master.items')
            ->whereHas('master', function ($query) {
                $query->whereNull('deleted_at');
            })->get();

        /** @var \App\Models\ChecklistSchedule $schedule */
        foreach ($schedules as $schedule) {

            if ($schedule->end_date && $today->isAfter($schedule->end_date)) {
                continue; // Lanjut ke jadwal berikutnya
            }
            if ($this->isDueToday($schedule, $today)) {

                $submissionExists = ChecklistSubmission::where('checklist_schedule_id', $schedule->id)
                    ->whereDate('submission_date', $today->toDateString())
                    ->exists();

                if (!$submissionExists) {
                    $this->generateSubmissionForSchedule($schedule, $today);
                    $this->line("Generated submission for schedule: '{$schedule->schedule_name}'");
                    Log::info("Scheduler Job: Generated submission for schedule '{$schedule->schedule_name}' (ID: {$schedule->id})");
                }
            }
        }

        Log::info('Scheduler Job: Finished GenerateDailyChecklists.');
        $this->info('Daily checklist generation finished successfully!');
        return 0;
    }

    /**
     * Cek apakah jadwal jatuh tempo hari ini.
     */
    private function isDueToday(ChecklistSchedule $schedule, Carbon $today): bool
    {

        switch ($schedule->periode_type) {
            case 'harian':
                return true;
            case 'mingguan':
                return in_array(strtolower($today->format('l')), $schedule->schedule_details ?? []);
            case 'bulanan':
                return in_array($today->day, $schedule->schedule_details ?? []);
            case 'tertentu':
                return in_array($today->toDateString(), $schedule->schedule_details ?? []);
            default:
                return false;
        }
    }

    /**
     * Membuat data submission dan detailnya.
     */
    private function generateSubmissionForSchedule(ChecklistSchedule $schedule, Carbon $today): void
    {

        DB::transaction(function () use ($schedule, $today) {
            $submission = ChecklistSubmission::create([
                'checklist_schedule_id' => $schedule->id,
                'submission_date' => $today->toDateString(),
                'submitted_by' => $schedule->created_by,
                'status' => 'pending',
            ]);

            foreach ($schedule->master->items as $item) {
                ChecklistSubmissionDetail::create([
                    'submission_id' => $submission->id,
                    'item_id' => $item->id,
                    'is_checked' => false,
                ]);
            }
        });
    }
}