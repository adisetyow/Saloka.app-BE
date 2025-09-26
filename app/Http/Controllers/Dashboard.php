<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;
use DateTime;

class Dashboard extends Controller
{
# Function COUNT TICKET
    public function getTotTicket($request)
    {
        try
        {
            // declare variable set
            $ticketStart =''; $ticketFinish='';
            if (isset($request['ticket_start'])) {$ticketStart = $request['ticket_start'];}
            if (isset($request['ticket_finish'])) {$ticketFinish = $request['ticket_finish'];}

            $data_ = DB::table('ticket_mst')
            ->select(DB::raw('Count(id) as total'));
            if($data_!='')
            {
                $data_->whereBetween('ticket_start', array($ticketStart." 00:00:00", $ticketFinish." 23:59:59"));
            }
            $result = $data_->first();

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    public function getTotTicketOpen($request)
    {
        try
        {
            // declare variable set
            $ticketStart =''; $ticketFinish='';
            if (isset($request['ticket_start'])) {$ticketStart = $request['ticket_start'];}
            if (isset($request['ticket_finish'])) {$ticketFinish = $request['ticket_finish'];}

            $data_ = DB::table('ticket_mst')
            ->select(DB::raw('Count(id) as total'))
            ->where('status','1');
            if($data_!='')
            {
                $data_->whereBetween('ticket_start', array($ticketStart." 00:00:00", $ticketFinish." 23:59:59"));
            }
            $result = $data_->first();

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    public function getTotTicketClose($request)
    {
        try
        {
            // declare variable set
            $ticketStart =''; $ticketFinish='';
            if (isset($request['ticket_start'])) {$ticketStart = $request['ticket_start'];}
            if (isset($request['ticket_finish'])) {$ticketFinish = $request['ticket_finish'];}

            $data_ = DB::table('ticket_mst')
            ->select(DB::raw('Count(id) as total'))
            ->where('status','9');
            if($data_!='')
            {
                $data_->whereBetween('ticket_start', array($ticketStart." 00:00:00", $ticketFinish." 23:59:59"));
            }
            $result = $data_->first();

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }
    }
# END REGION

# Function TOP DEPARTEMEN
    public function getTopDepartemen($request)
    {
        try
        {
            // declare variable set
            $ticketStart =''; $ticketFinish='';
            if (isset($request['ticket_start'])) {$ticketStart = $request['ticket_start'];}
            if (isset($request['ticket_finish'])) {$ticketFinish = $request['ticket_finish'];}
            
            $data_ = DB::table('ticket_mst')
            ->select('departemen',DB::raw('Count(id) as total'))
            ->groupBy('departemen');
            if($data_!='')
            {
                $data_->whereBetween('ticket_start', array($ticketStart." 00:00:00", $ticketFinish." 23:59:59"));
            }
            $result = $data_->get();
            
            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }
    }
# END REGION

# Function TOP CATEGORY
public function getTopCategory($request)
{
    try
    {
        // declare variable set
        $ticketStart =''; $ticketFinish='';
        if (isset($request['ticket_start'])) {$ticketStart = $request['ticket_start'];}
        if (isset($request['ticket_finish'])) {$ticketFinish = $request['ticket_finish'];}
        
        $data_ = DB::table('ticket_mst')
        ->select('category',DB::raw('Count(id) as total'))
        ->groupBy('category');
        if($data_!='')
        {
            $data_->whereBetween('ticket_start', array($ticketStart." 00:00:00", $ticketFinish." 23:59:59"));
        }
        $result = $data_->get();
        
        return $result;
    } catch (\Exception $ex) {
        return $ex;
    }
}
# END REGION

}
