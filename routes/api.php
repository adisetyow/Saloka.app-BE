<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Service_Ticket;
use App\Http\Controllers\Service_Master;
use App\Http\Controllers\Service_Dashboard;
use App\Http\Controllers\Service_SyncTransaction;
use App\Http\Controllers\Ticket;
use App\Http\Controllers\Service_Checklist;
use App\Http\Controllers\ChecklistTypeController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::controller(Service_Ticket::class)->group(function () {
    // Import Ticket From Excel
    Route::post('import_ticketMst', 'importTicketFromExcel');
    // Request Ticket
    Route::post('request_ticket', 'requestTicket');
    // Edit Ticket
    Route::post('edit_request_ticket', 'editRequestTicket');
    // Add Job Ticket
    Route::post('add_job_ticket', 'addJobTicket');
    // Edit Job Ticket
    Route::post('edit_job_ticket', 'editJobTicket');
    // Edit Ticket
    Route::post('declare_close_ticket', 'declareCloseTicket');

    // Get Ticket
    Route::get('get_ticket', 'getTicket');
});

Route::controller(Service_Master::class)->group(function () {
    // Get Category
    Route::get('get_category', 'getCategory');
    // Insert Category
    Route::post('insert_category', 'insertCategory');
    // Update Category
    Route::post('update_category', 'updateCategory');
});

Route::get('checklist-types', [ChecklistTypeController::class, 'index']);
Route::post('checklist-types', [ChecklistTypeController::class, 'store']);

Route::controller(Service_Checklist::class)->group(function () {
    Route::get('checklist-masters', 'getAllMasters');
    Route::post('checklist-masters', 'storeMaster');
    Route::get('checklist-masters/{id}', 'getMasterById');
    Route::put('checklist-masters/{id}', 'updateMaster');
    Route::delete('checklist-masters/{id}', 'destroyMaster');

    Route::get('checklist-schedules', 'getAllSchedules');
    Route::get('checklist-schedules/{id}', 'getScheduleById');
    Route::post('checklist-schedules', 'storeSchedule');
    Route::put('checklist-schedules/{id}', 'updateSchedule');
    Route::delete('checklist-schedules/{id}', 'destroySchedule');
    Route::post('start-checklist/{schedule}', 'startChecklist');
    Route::get('todays-checklists', 'getTodaysSchedules');

    Route::get('checklist-submissions', 'getDailySubmissions');
    Route::get('checklist-submissions/{submissionId}', 'getSubmissionDetail');
    Route::post('checklist-submission-details/{submissionDetailId}', 'checkSubmissionItem');
});


Route::controller(Service_Dashboard::class)->group(function () {
    // Get Dashboard
    Route::get('get_dashboard', 'getDashboard');
});

Route::controller(Ticket::class)->group(function () {
    // Import Ticket From Excel
    Route::post('import_ticketMst', 'importTicketFromExcel');
});

Route::controller(Service_SyncTransaction::class)->group(function () {
    Route::post('sync_from_work_order', 'sync');
});

