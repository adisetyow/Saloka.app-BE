<?php

namespace App\Http\Controllers;

use App\Models\ChecklistSchedule;
use App\Models\ChecklistSubmission;
use App\Models\ChecklistSubmissionDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception; // Pastikan Exception di-import

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

        if ($request->has('date') && $request->input('date') != '') {
            $targetDate = Carbon::parse($request->input('date'));
            $query->whereDate('submission_date', $targetDate->toDateString());
        }

        $submissions = $query->latest('submission_date')->get();

        return $submissions;
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

        return $submission;
    }

    /**
     * Menyimpan aksi ceklis dari user.
     */
    public function storeCheck(Request $request, $submissionDetailId)
    {
        // ... (Fungsi ini sudah benar, tidak perlu diubah)
        $validator = Validator::make($request->all(), [
            'is_checked' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
            'id_karyawan' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()], 422);
        }

        $detail = ChecklistSubmissionDetail::findOrFail($submissionDetailId);
        $submission = $detail->submission;

        $user = \App\Models\User::firstOrCreate(['karyawan_id' => $request->id_karyawan]);

        $detail->update([
            'is_checked' => $request->is_checked,
            'notes' => $request->notes
        ]);

        $submission->update(['submitted_by' => $user->id]);
        $this->updateSubmissionStatus($submission);

        return $detail;
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
        
        return $submission;
    }
    


    /**
     * Update status submission.
     */
    private function updateSubmissionStatus(ChecklistSubmission $submission)
    {
        // ... (Fungsi ini sudah benar, tidak perlu diubah)
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