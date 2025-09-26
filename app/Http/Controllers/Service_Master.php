<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class Service_Master extends Controller
{
    public function getCategory(Request $request)
    {
        try
        {
            $ticket = new Master();
            $data = $ticket->getCategory($request); 
            
            $result=response()->json([
                'status' => 'success',
                'message' => 'Get Category Successfuly',
                'data' => $data
            ]);

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }  
    }

    public function insertCategory(Request $request)
    {
        try
        {
            $ticket = new Master();
            $data = $ticket->insertCategory($request); 
            
            $result=response()->json([
                'status' => 'success',
                'message' => 'Insert Category Successfuly',
                'data' => $data
            ]);

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }  
    }

    public function updateCategory(Request $request)
    {
        try
        {
            $ticket = new Master();
            $data = $ticket->updateCategory($request); 
            
            $result=response()->json([
                'status' => 'success',
                'message' => 'Update Category Successfuly',
                'data' => $data
            ]);

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }   
    }
}
