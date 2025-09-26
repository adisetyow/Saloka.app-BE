<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\ticket_log;
use Carbon\Carbon;
use DateTime;

class Class_TicketLogs extends Controller
{
      /**
     * Read table
     */
    public function show($request)
    {
        // set value variable
        $idTicket = '';
        $userId = '';
        $name = '';
        $activity = '';
        $detailAct = '';
      
        // declare variable set
        if (isset($request['id_ticket'])) {$idTicket = $request['id_ticket'];}
        if (isset($request['user_id'])) {$userId = $request['user_id'];}
        if (isset($request['name'])) {$name = $request['name'];}
        if (isset($request['activity'])) {$activity = $request['activity'];}
        if (isset($request['detail_act'])) {$detailAct = $request['detail_act'];}

        try
        {
            $data_ = DB::table('ticket_log');
            if($idTicket!='')
            {
                $data_->where('id_ticket',$idTicket);
            }
            if($userId!='')
            {
                $data_->where('user_id',$userId);
            }
            if($name!='')
            {
                $data_->where('name','like','%'.$name.'%');
            }
            if($activity!='')
            {
                $data_->where('activity',$activity);
            }
            if($detailAct!='')
            {
                $data_->where('detail_act','like','%'.$detailAct.'%');
            }

            if($data_->exists())
            {
                $data = $data_->get();
            }
            else
            {
                $data = null;
            }
            return $data;
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    /**
     * Create table
     */
    public function insert($request)
    {
        // set value variable
        $idWo = '';
        $userId = '';
        $name = '';
        $activity = '';
        $detailAct = '';
  
        // declare variable set
        if (isset($request['id_ticket']) && $request['id_ticket']!='') {$idTicket = $request['id_ticket'];}
        if (isset($request['user_id']) && $request['user_id']!='') {$userId = $request['user_id'];}
        if (isset($request['name']) && $request['name']!='') {$name = $request['name'];}
        if (isset($request['activity']) && $request['activity']!='') {$activity = $request['activity'];}
        if (isset($request['detail_act']) && $request['detail_act']!='') {$detailAct = $request['detail_act'];}
       
        try
        {
            $data = new ticket_log();
            $data->id_ticket = $idTicket;
            $data->user_id = $userId;
            $data->name = $name;
            $data->activity = $activity; 
            $data->detail_act = $detailAct;   
            $data->save();
            return $data;
        } catch (\Exception $ex) {
            dd($ex);
            return $ex;
        }
    }

    public function delete($request)
    {
        try
        {
            $idTicket='';
            if (isset($request['id_ticket']) && $request['id_ticket']!='') 
            {
                $idTicket = $request['id_ticket'];
                DB::table('ticket_log')
                ->where('id_ticket','=',$idTicket)
                ->delete();

                return 'success';
            }
            return 'data not found';
        } catch (\Exception $ex) {
            return $ex;
        }
    }
}
