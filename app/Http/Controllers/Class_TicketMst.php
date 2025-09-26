<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\ticket_mst;
use Carbon\Carbon;
use DateTime;

class Class_TicketMst extends Controller
{
   /**
     * Read table
     */
    public function show($request)
    {
        // set value variable
        $idTicket = ''; $status=''; $type=''; $priority=''; $category=''; $subject=''; $description=''; $file=''; $createdBy=''; $departemen=''; $assignee=''; $ticketStart=''; $ticketFinish=''; $closingBy='';
        
        // declare variable set
        if (isset($request['id_ticket'])) {$idTicket = $request['id_ticket'];}
        if (isset($request['status'])) {$status = $request['status'];}
        if (isset($request['type'])) {$type = $request['type'];}
        if (isset($request['priority'])) {$priority = $request['priority'];}
        if (isset($request['category'])) {$category = $request['category'];}
        if (isset($request['subject'])) {$subject = $request['subject'];}
        if (isset($request['description'])) {$description = $request['description'];}
        if (isset($request['file'])) {$file = $request['file'];}
        if (isset($request['created_by'])) {$createdBy = $request['created_by'];}
        if (isset($request['departemen'])) {$departemen = $request['departemen'];}
        if (isset($request['assignee'])) {$assignee = $request['assignee'];}
        if (isset($request['ticket_start'])) {$ticketStart = $request['ticket_start'];}
        if (isset($request['ticket_finish'])) {$ticketFinish = $request['ticket_finish'];}
        if (isset($request['closing_by'])) {$closingBy = $request['closing_by'];}

        try
        {
            $data_ = DB::table('ticket_mst');
            $urlServer = config('app.apiITDesk');

            $data_->select('id','id_ticket','status','type','priority','category','subject','description', 
            DB::raw("CONCAT('".$urlServer."/storage/ticket_master/',file) as file"),
            'created_by','departemen','assignee','ticket_start','ticket_finish','time_solved','closing_by','description_trouble','description_solution',
            'created_at','updated_at');
            if($idTicket!='')
            {
                $data_->where('id_ticket',$idTicket);
            }
            if($status!='')
            {
                $data_->where('status',$status);
            }
            if($type!='')
            {
                $data_->where('type',$type);
            }
            if($priority!='')
            {
                $data_->where('priority',$priority);
            }
            if($category!='')
            {
                $data_->where('category',$category);
            }
            if($subject!='')
            {
                $data_->where('subject',$subject);
            }
            if($description!='')
            {
                $data_->where('description',$description);
            }
            if($file!='')
            {
                $data_->where('file',$file);
            }
            if($createdBy!='')
            {
                $data_->where('created_by',$createdBy);
            }
            if($departemen!='')
            {
                $data_->where('departemen',$departemen);
            }
            if($assignee!='')
            {
                $data_->where('assignee',$assignee);
            }
            if($ticketStart!='')
            {
                $data_->whereBetween('ticket_start', array($ticketStart." 00:00:00", $ticketFinish." 23:59:59"));
            }
            if($closingBy!='')
            {
                $data_->where('closing_by',$closingBy);
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
        $idTicket = ''; $status=''; $type=''; $priority=''; $category=''; $subject=''; $description=''; $file=''; $createdBy=''; $departemen=''; $assignee=''; $ticketStart=''; $ticketFinish=null; $closingBy='';
        $descriptionTrouble=''; $descriptionSolution=''; $timeSolved='';
        // declare variable set
        if (isset($request['id_ticket']) && $request['id_ticket']!='') {$idTicket = $request['id_ticket'];}
        if (isset($request['status']) && $request['status']!='') {$status = $request['status'];}
        if (isset($request['type']) && $request['type']!='') {$type = $request['type'];}
        if (isset($request['priority']) && $request['priority']!='') {$priority = $request['priority'];}
        if (isset($request['category']) && $request['category']!='') {$category = $request['category'];}
        if (isset($request['subject']) && $request['subject']!='') {$subject = $request['subject'];}
        if (isset($request['description']) && $request['description']!='') {$description = $request['description'];}
        if (isset($request['file']) && $request['file']!='') {$file = $request['file'];}
        if (isset($request['created_by']) && $request['created_by']!='') {$createdBy = $request['created_by'];}
        if (isset($request['departemen']) && $request['departemen']!='') {$departemen = $request['departemen'];}
        if (isset($request['assignee']) && $request['assignee']!='') {$assignee = $request['assignee'];}
        if (isset($request['ticket_start']) && $request['ticket_start']!='') {$ticketStart = $request['ticket_start'];}
        if (isset($request['ticket_finish']) && $request['ticket_finish']!='') {$ticketFinish = $request['ticket_finish'];}
        if (isset($request['time_solved']) && $request['time_solved']!='') {$timeSolved = $request['time_solved'];}
        if (isset($request['description_trouble']) && $request['description_trouble']!='') {$descriptionTrouble = $request['description_trouble'];}
        if (isset($request['description_solution']) && $request['description_solution']!='') {$descriptionSolution = $request['description_solution'];}
        if (isset($request['closing_by']) && $request['closing_by']!='') {$closingBy = $request['closing_by'];}
        
        try
        {
            // cek data
            $request=[];
            $request['id_ticket'] = $idTicket;
            $request['status'] = $status;
            $request['type'] = $type;
            $request['priority'] = $priority;
            $request['category'] = $category;
            $request['subject'] = $subject;
            $request['description'] = $description;
            $request['file'] = $file;
            $request['created_by'] = $createdBy;
            $request['departemen'] = $departemen;
            $request['assignee'] = $assignee;
            $request['ticket_start'] = $ticketStart;
            $request['ticket_finish'] = $ticketFinish;
            $request['closing_by'] = $closingBy;
            
            $dataTransaction = $this->show($request);
     
            if(isset($dataTransaction))
            {
                // data sudah ada
                return 'double data';
            }
            else
            {
                $data = new ticket_mst();
                $data->id_ticket = $idTicket;
                $data->status = $status;
                $data->type = $type;
                $data->priority = $priority; 
                $data->category = $category; 
                $data->subject = $subject; 
                $data->description = $description; 
                $data->file = $file; 
                $data->created_by = $createdBy; 
                $data->departemen = $departemen; 
                $data->assignee = $assignee; 
                $data->ticket_start = $ticketStart; 
                $data->ticket_finish = $ticketFinish; 
                $data->time_solved = $timeSolved;
                $data->description_trouble = $descriptionTrouble;
                $data->description_solution = $descriptionSolution;
                $data->closing_by = $closingBy; 
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
        $idTicket = '';
        $updateData =[];
        try
        {
            // declare variable set
            if (isset($request['id_ticket'])) {$idTicket = $request['id_ticket'];}
            if (isset($request['status'])) {$updateData['status'] = $request['status'];}
            if (isset($request['type'])) {$updateData['type'] = $request['type'];}
            if (isset($request['priority'])) {$updateData['priority'] = $request['priority'];}
            if (isset($request['category'])) {$updateData['category'] = $request['category'];}
            if (isset($request['subject'])) {$updateData['subject'] = $request['subject'];}
            if (isset($request['description'])) {$updateData['description'] = $request['description'];}
            if (isset($request['file'])) {$updateData['file'] = $request['file'];}
            if (isset($request['departemen'])) {$updateData['departemen'] = $request['departemen'];}
            if (isset($request['assignee'])) {$updateData['assignee'] = $request['assignee'];}
            if (isset($request['ticket_start'])) {$updateData['ticket_start'] = $request['ticket_start'];}
            if (isset($request['ticket_finish'])) {$updateData['ticket_finish'] = $request['ticket_finish'];}
            if (isset($request['time_solved'])) {$updateData['time_solved'] = $request['time_solved'];}
            if (isset($request['description_trouble'])) {$updateData['description_trouble'] = $request['description_trouble'];}
            if (isset($request['description_solution'])) {$updateData['description_solution'] = $request['description_solution'];}
            if (isset($request['closing_by'])) {$updateData['closing_by'] = $request['closing_by'];}
       
            DB::table('ticket_mst')
            ->where('id_ticket','=',$idTicket)
            ->update($updateData);

            return $updateData;
        } catch (\Exception $ex) {
            return $ex;
        }
    }

    public function deleted($request)
    {
        try
        {
            $idTicket='';
            if (isset($request['id_ticket']) && $request['id_ticket']!='') 
            {
                DB::table('ticket_mst')
                ->where('id_ticket','=',$request['id_ticket'])
                ->delete();

                return 'success';
            }
            return 'data not found';
        } catch (\Exception $ex) {
            return $ex;
        }
    }
}
