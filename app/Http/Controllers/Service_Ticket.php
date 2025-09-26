<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class Service_Ticket extends Controller
{
    /**CREATE REQUEST Ticket
     * PARAM REQUEST : 
     */
    public function requestTicket(Request $request)
    {
        try
        {
            $ticket = new Ticket();
            $data = $ticket->requestTicket($request); 
            
            $result=response()->json([
                'status' => 'success',
                'message' => 'Insert Request Ticket Successfuly',
                'data' => $data
            ]);

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    public function editRequestTicket(Request $request)
    {
        try
        {
            $ticket = new Ticket();
            $data = $ticket->editReqeustTicket($request); 
            
            $result=response()->json([
                'status' => 'success',
                'message' => 'Edit Ticket Successfuly',
                'data' => $data
            ]);

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    public function addJobTicket(Request $request)
    {
        try
        {
            $ticket = new Ticket();
            $data = $ticket->addJobTicket($request); 
            
            $result=response()->json([
                'status' => 'success',
                'message' => 'Add Job Ticket Successfuly',
                'data' => $data
            ]);

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }
    }
    
    public function editJobTicket(Request $request)
    {
        try
        {
            $ticket = new Ticket();
            $data = $ticket->editJobTicket($request); 
            
            $result=response()->json([
                'status' => 'success',
                'message' => 'Edit Job Ticket Successfuly',
                'data' => $data
            ]);

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    public function declareCloseTicket(Request $request)
    {
        try
        {
            $ticket = new Ticket();
            $data = $ticket->declareCloseTicket($request); 
            
            $result=response()->json([
                'status' => 'success',
                'message' => 'Close Ticket Successfuly',
                'data' => $data
            ]);

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    public function getTicket(Request $request)
    {
        try
        {
            $ticket = new Ticket();
            $data = $ticket->getRequestTicket($request); 
            
            $result=response()->json([
                'status' => 'success',
                'message' => 'Get Ticket Successfuly',
                'data' => $data
            ]);

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }
    }
}
