<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\Import_TicketMst;

use Carbon\Carbon;
use DateTime;

class Master extends Controller
{
    public function getCategory($request)
    {
        try
        {
            $requestModule=[];
            if (isset($request['category']) && $request['category']!='' ) {$requestModule['category'] = $request['category'];}

            $result=[];
            $classMasterCategory = new Class_MasterCategory();
            $result = $classMasterCategory->show($request);

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    public function insertCategory($request)
    {
        try
        {
            $requestModule=[];
            if (isset($request['category']) && $request['category']!='' ) {$requestModule['category'] = $request['category'];}

            $result=[];
            $classMasterCategory = new Class_MasterCategory();
            $result = $classMasterCategory->insert($request);

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    public function updateCategory($request)
    {
        try
        {
            $requestModule=[];
            if (isset($request['id']) && $request['id']!='' ) {$requestModule['id'] = $request['id'];}
            if (isset($request['category']) && $request['category']!='' ) {$requestModule['category'] = $request['category'];}
            if (isset($request['is_active']) && $request['is_active']!='' ) {$requestModule['is_active'] = $request['is_active'];}

            $result=[];
            $classMasterCategory = new Class_MasterCategory();
            $result = $classMasterCategory->update($request);

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }
    }
}
