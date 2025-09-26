<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// package external
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Carbon\Carbon;

class Class_GenerateID extends Controller
{
    public function getIDTicket()
    {
        
        $id='-';
        $prefix = 'ID-'.date('ymd');
        $prefixSubs = substr($prefix,0,7);

        $count = DB::table('ticket_mst')
        ->select(DB::raw('COUNT(ID) as count'))
        ->where('id_ticket','like','%'.$prefixSubs.'%')
        ->first();
        $formattedNumber = str_pad($count->count, 6, '0', STR_PAD_LEFT);
      
        $id = $prefix.'-'.$formattedNumber;
        return $id;
    }
}
