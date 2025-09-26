<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Class_TicketMst;

class Import_TicketMst implements ToModel,WithHeadingRow
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        try {
            DB::beginTransaction();

            $request =[];
            $request['id_ticket'] = $row['id_ticket'];
            $request['status'] = $row['status'];
            $request['type'] = $row['type'];
            $request['priority'] = $row['priority'];
            $request['category'] = $row['category'];
            $request['subject'] = $row['subject'];
            $request['description'] = $row['description'];
            $request['file'] = $row['file'];
            $request['created_by'] = $row['created_by'];
            $request['departemen'] = $row['departemen'];
            $request['assignee'] = $row['assignee'];
            $request['ticket_start'] = $row['ticket_start'];
            $request['ticket_finish'] = $row['ticket_finish'];
            $request['time_solved'] = $row['time_solved'];
            $request['description_trouble'] = $row['description_trouble'];
            $request['description_solution'] = $row['description_solution'];
            $request['closing_by'] = $row['closing_by'];

            $classTicketMst = new Class_TicketMst();
            $result = $classTicketMst->insert($request);
           

            DB::commit();
        } catch (\Exception $ex) {
            DB::rollBack();
            dd($ex);
            return response()->json($ex);
        }
    }
}
