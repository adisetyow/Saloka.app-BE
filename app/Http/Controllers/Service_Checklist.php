<?php

namespace App\Http\Controllers;

use App\Models\ChecklistMaster;
use App\Http\Controllers\ChecklistSubmissionController;
use App\Models\ChecklistSchedule;
use Illuminate\Http\Request;

class Service_Checklist extends Controller
{
    protected $checklistMasterController;
    protected $checklistScheduleController;
    protected $checklistSubmissionController;

    public function __construct(
        ChecklistMasterController $checklistMasterController,
        ChecklistScheduleController $checklistScheduleController,
        ChecklistSubmissionController $checklistSubmissionController
    ) {
        $this->checklistMasterController = $checklistMasterController;
        $this->checklistScheduleController = $checklistScheduleController;
        $this->checklistSubmissionController = $checklistSubmissionController;
    }

    /**
     * Endpoint untuk mengambil semua master checklist
     */
    public function getAllMasters()
    {
        try {
            $data = $this->checklistMasterController->index();
            return response()->json([
                'status' => 'success',
                'message' => 'Master Checklists retrieved successfully',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint untuk menyimpan master checklist baru
     */
    public function storeMaster(Request $request)
    {
        try {
            $data = $this->checklistMasterController->store($request);

            // Cek jika ada error validasi dari controller logic
            if ($data instanceof \Illuminate\Http\JsonResponse) {
                return $data;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Master Checklist created successfully',
                'data' => $data
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint untuk mengambil satu master checklist
     */
    public function getMasterById($id)
    {
        try {
            $checklistMaster = ChecklistMaster::findOrFail($id);
            $data = $this->checklistMasterController->show($checklistMaster);
            return response()->json([
                'status' => 'success',
                'message' => 'Master Checklist retrieved successfully',
                'data' => $data
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Checklist not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }


    public function updateMaster(Request $request, $id)
    {
        try {
            $checklistMaster = ChecklistMaster::findOrFail($id);
            $data = $this->checklistMasterController->update($request, $checklistMaster);

            // Cek jika ada error validasi dari controller logic
            if ($data instanceof \Illuminate\Http\JsonResponse) {
                return $data;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Master Checklist updated successfully',
                'data' => $data
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Checklist not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
    /**
     * Endpoint untuk menghapus master checklist
     */
    public function destroyMaster($id)
    {
        try {
            $checklistMaster = ChecklistMaster::findOrFail($id);
            return $this->checklistMasterController->destroy($checklistMaster);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Checklist not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getAllSchedules()
    {
        try {
            $data = $this->checklistScheduleController->index();
            return response()->json([
                'status' => 'success',
                'message' => 'Schedules retrieved successfully',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getScheduleById($id)
    {
        try {
            $schedule = ChecklistSchedule::findOrFail($id);
            $data = $this->checklistScheduleController->show($schedule);
            return response()->json([
                'status' => 'success',
                'message' => 'Schedule retrieved successfully',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Schedule not found'], 404);
        }
    }

    public function storeSchedule(Request $request)
    {
        try {
            $data = $this->checklistScheduleController->store($request);

            if ($data instanceof \Illuminate\Http\JsonResponse) {
                return $data; // Mengembalikan error validasi jika ada
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Checklist schedule created successfully',
                'data' => $data
            ], 201);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function updateSchedule(Request $request, $id)
    {
        try {
            $schedule = ChecklistSchedule::findOrFail($id);
            $data = $this->checklistScheduleController->update($request, $schedule);

            if ($data instanceof \Illuminate\Http\JsonResponse) {
                return $data;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Schedule updated successfully',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint untuk menghapus jadwal.
     */
    public function destroySchedule($id)
    {
        try {
            $schedule = ChecklistSchedule::findOrFail($id);
            return $this->checklistScheduleController->destroy($schedule);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Schedule not found'], 404);
        }
    }

    public function getDailySubmissions(Request $request)
    {
        try {
            $data = $this->checklistSubmissionController->index($request);
            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint untuk mendapatkan detail item dari satu submission.
     */
    public function getSubmissionDetail($submissionId)
    {
        try {
            $data = $this->checklistSubmissionController->show($submissionId);
            return response()->json(['status' => 'success', 'data' => $data]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint untuk melakukan aksi ceklis pada satu item.
     */
    public function checkSubmissionItem(Request $request, $submissionDetailId)
    {
        try {
            $data = $this->checklistSubmissionController->storeCheck($request, $submissionDetailId);

            if ($data instanceof \Illuminate\Http\JsonResponse) {
                return $data;
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Item updated',
                'data' => $data
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 'error', 'message' => 'Submission detail not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function getTodaysSchedules()
    {
        try {
            $data = $this->checklistScheduleController->getTodaysSchedules();
            return response()->json([
                'status' => 'success',
                'message' => 'Today\'s schedules retrieved successfully',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }


    public function startChecklist(ChecklistSchedule $schedule)
    {
        try {
            $data = $this->checklistSubmissionController->startOrGetTodaySubmission($schedule);

            return response()->json([
                'status' => 'success',
                'message' => 'Submission ready',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 422);
        }
    }
}