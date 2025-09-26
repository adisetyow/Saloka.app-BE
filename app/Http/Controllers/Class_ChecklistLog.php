<?php

namespace App\Http\Controllers;

use App\Models\ChecklistLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Class_ChecklistLog extends Controller
{
    /**
     * Create table
     */
    public function insert($request)
    {
        try {
            $data = ChecklistLog::create([
                'checklist_master_id' => $request['checklist_master_id'],
                'user_id' => $request['user_id'],
                'name' => $request['name'],
                'activity' => $request['activity'],
                'detail_act' => $request['detail_act'],
            ]);

            return $data;
        } catch (\Exception $ex) {
            return $ex;
        }
    }
}