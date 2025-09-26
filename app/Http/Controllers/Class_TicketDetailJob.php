<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\ticket_detail_job;
use Carbon\Carbon;
use DateTime;

class Class_TicketDetailJob extends Controller
{
    /**
     * Read table
     */
    public function show($request)
    {
        // set value variable
        $idTicket = '';
        $subject = '';
        $detailJob = '';
        $file = '';
        $picName = '';
        
        // declare variable set
        if (isset($request['id_ticket'])) {$idTicket = $request['id_ticket'];}
        if (isset($request['subject'])) {$subject = $request['subject'];}
        if (isset($request['detail_job'])) {$detailJob = $request['detail_job'];}
        if (isset($request['file'])) {$file = $request['file'];}
        if (isset($request['pic_name'])) {$picName = $request['pic_name'];}

        try
        {
            $urlServer = config('app.apiITDesk');
            $data_ = DB::table('ticket_detail_job');

            $data_->select('id','id_ticket','subject','detail_job',
            DB::raw("CONCAT('".$urlServer."/storage/ticket_master/',file) as file"),
            'pic_name',
            'created_at','updated_at');
            if($idTicket!='')
            {
                $data_->where('id_ticket',$idTicket);
            }
            if($subject!='')
            {
                $data_->where('subject',$subject);
            }
            if($detailJob!='')
            {
                $data_->where('detail_job',$detailJob);
            }
            if($file!='')
            {
                $data_->where('file',$file);
            }
            if($picName!='')
            {
                $data_->where('pic_name',$picName);
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
        $idTicket = '';
        $subject = '';
        $detailJob = '';
        $file = '';
        $picName = '';
        
        // declare variable set
        if (isset($request['id_ticket']) && $request['id_ticket']!='') {$idTicket = $request['id_ticket'];}
        if (isset($request['subject']) && $request['subject']!='') {$subject = $request['subject'];}
        if (isset($request['detail_job']) && $request['detail_job']!='') {$detailJob = $request['detail_job'];}
        if (isset($request['file']) && $request['file']!='') {$file = $request['file'];}
        if (isset($request['pic_name']) && $request['pic_name']!='') {$picName = $request['pic_name'];}
        
        try
        {
            // cek data
            $request=[];
            $request['id_ticket'] = $idTicket;
            $request['subject'] = $subject;
            $request['detail_job'] = $detailJob;
            $request['file'] = $file;
            $request['pic_name'] = $picName;

            $dataTransaction = $this->show($request);
     
            if(isset($dataTransaction))
            {
                // data sudah ada
                return 'double data';
            }
            else
            {
                $data = new ticket_detail_job();
                $data->id_ticket = $idTicket;
                $data->subject = $subject;
                $data->detail_job = $detailJob;
                $data->file = $file; 
                $data->pic_name = $picName;   
                $data->save();
            }
            return $data;
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    /**
     * Update table
     */
    public function update($request)
    {
     
        // set value variable
        $id=''; $idTicket = '';
        $updateData =[];
        try
        {
            // declare variable set
            if (isset($request['id'])) {$id = $request['id'];}
            if (isset($request['id_ticket'])) {$idTicket = $request['id_ticket'];}
            if (isset($request['subject'])) {$updateData['subject'] = $request['subject'];}
            if (isset($request['detail_job'])) {$updateData['detail_job'] = $request['detail_job'];}
            if (isset($request['pic_name'])) {$updateData['pic_name'] = $request['pic_name'];}
            if (isset($request['file'])) {$updateData['file'] = $request['file'];}

            DB::table('ticket_detail_job')
            ->where('id','=',$id)
            ->where('id_ticket','=',$idTicket)
            ->update($updateData);
   
            return 'success';
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
                DB::table('ticket_detail_job')
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
