<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;

class Service_SyncTransaction extends Controller
{
    public function sync(Request $request)
    {
        try
        {   
            $action = $request['action'];
            $classTransaction = $request['classTransaction'];
            $data = $request['transaction'];

            $transactionDetail=''; $transactionLog='';
            if (isset($request['transaction_detail']) && $request['transaction_detail']!=null) {$transactionDetail = $request['transaction_detail'];}
            if (isset($request['transaction_log']) && $request['transaction_log']!=null) {$transactionLog = $request['transaction_log'];}
         
            $result=[]; $request=[];
            $request['action'] = $action;
            $request['data'] = $data;
         
            if($classTransaction =='transactionWoMst') // call function syncTransactionMst
            {
                $request['data_detail'] = $transactionDetail;
                $request['data_log'] = $transactionLog;
              
                $result = $this->syncTransactionMst($request);
                return response()->json(['status' => 'success','data' => $result]);
            }
        }
        catch(\Exception $e)
        {
            return response()->json(['status' => 'error','message' => $e]);
        }
    }

    private function syncTransactionMst($request)
    {
        try
        {
            $data = $request['data'];
            $dataDetail = $request['data_detail'];
            $dataLog = $request['data_log'];
            $action = $request['action'];
            
    
            $idTicket=''; $status=''; $type=''; $priority=''; $category=''; $subject=''; $description=''; $file=''; 
            $createdBy=''; $departemen=''; $assignee=''; $ticketStart=''; $ticketFinish=''; $timeSolve=''; 
            $descriptionTrouble=''; $descriptionSolution=''; $closeBy='';
        
            if (isset($data['id_wo']) && $data['id_wo']!='') {$idTicket = $data['id_wo'];}
            if (isset($data['priority']) && $data['priority']!='') {$priority = $data['priority'];}
            // if (isset($data['job_name']) && $data['job_name']!='') {$subject = $data['job_name'];}
            if (isset($data['job_description']) && $data['job_description']!='') {$subject = $data['job_description'];}
            if (isset($data['description_of_work_order']) && $data['description_of_work_order']!='') {$description = $data['description_of_work_order'];}
            if (isset($data['job_image']) && $data['job_image']!='') {$file = $data['job_image'];}
            if (isset($data['name_request']) && $data['name_request']!='') {$createdBy = $data['name_request'];}
            if (isset($data['departemen_request']) && $data['departemen_request']!='') {$departemen = $data['departemen_request'];}
            if (isset($data['date_request']) && $data['date_request']!='') {$ticketStart = $data['date_request'];}
    
            if (isset($data['status']) && $data['status']!='') {$status = $data['status'];}
            if (isset($data['type']) && $data['type']!='') {$type = $data['type'];}
            if (isset($data['category']) && $data['category']!='') {$category = $data['category'];}
            if (isset($data['ticket_finish']) && $data['ticket_finish']!='') {$ticketFinish = $data['ticket_finish'];}
            if (isset($data['time_solve']) && $data['time_solve']!='') {$timeSolve = $data['time_solve'];}
            if (isset($data['description_trouble']) && $data['description_trouble']!='') {$descriptionTrouble = $data['description_trouble'];}
            if (isset($data['description_solution']) && $data['description_solution']!='') {$descriptionSolution = $data['description_solution'];}
            if (isset($data['close_by']) && $data['close_by']!='') {$closeBy = $data['close_by'];}
     
            if ($action === 'created') {
                $requestClassDB = [];
                $requestClassDB['id_ticket'] = $idTicket;
                $requestClassDB['status'] = 'Open';
                $requestClassDB['type'] = 'Work Order';
                $requestClassDB['priority'] = $priority;
                $requestClassDB['category'] = '-';
                $requestClassDB['subject'] = $subject;
                $requestClassDB['description'] = $description;
                $requestClassDB['file'] = $file;
                $requestClassDB['created_by'] = $createdBy;
                $requestClassDB['departemen'] = $departemen;
                $requestClassDB['ticket_start'] = $ticketStart;
                
                $classDB = new Class_TicketMst();
                $resultClassDB = $classDB->insert($requestClassDB);
                return $resultClassDB;
            }
    
            if ($action === 'updated') {
                $updateData=[];
                if ($idTicket!='') {$updateData['id_ticket'] = $idTicket;}
                if ($status!='') {$updateData['status'] = $status;}
                if ($type!='') {$updateData['type'] = $type;}
                if ($priority!='') {$updateData['priority'] = $priority;}
                if ($category!='') {$updateData['category'] = $category;}
                if ($subject!='') {$updateData['subject'] = $subject;}
                if ($description!='') {$updateData['description'] = $description;}
                if ($file!='') {$updateData['file'] = $file;}
                if ($createdBy!='') {$updateData['created_by'] = $createdBy;}
                if ($departemen!='') {$updateData['departemen'] = $departemen;}
                if ($assignee!='') {$updateData['assignee'] = $assignee;}
                if ($ticketStart!='') {$updateData['ticket_start'] = $ticketStart;}
                if ($ticketFinish!='') {$updateData['ticket_finish'] = $ticketFinish;}
                if ($timeSolve!='') {$updateData['time_solve'] = $timeSolve;}
                if ($descriptionTrouble!='') {$updateData['description_trouble'] = $descriptionTrouble;}
                if ($descriptionSolution!='') {$updateData['description_solution'] = $descriptionSolution;}
                if ($closeBy!='') {$updateData['close_by'] = $closeBy;}
    
                $classDB = new Class_TicketMst();
                $resultClassDB['update_ticketMst'] = $classDB->update($updateData);
        
                if($dataDetail!='') {
                    // delete ticket detail job
                    $requestClassDB = [];
                    $requestClassDB['id_ticket'] = $idTicket;
                    $classDB = new Class_TicketDetailJob();
                    $resultClassDB['delete_ticketDetailJob'] = $classDB->delete($requestClassDB);
                  
                    // insert ticket detail Job
                    $updateDataTicketDetailJob=[];
                    $updateDataTicketDetailJob['id_ticket'] = $idTicket;
                    foreach($dataDetail as $dataDetail)
                    {
                        if (isset($dataDetail['subject'])) {$updateDataTicketDetailJob['subject'] = $dataDetail['subject'];}
                        if (isset($dataDetail['description'])) {$updateDataTicketDetailJob['detail_job'] = $dataDetail['description'];}
                        if (isset($dataDetail['file'])) {$updateDataTicketDetailJob['file'] = $dataDetail['file'];}
                        if (isset($dataDetail['pic_name'])) {$updateDataTicketDetailJob['pic_name'] = $dataDetail['pic_name'];}
                        $classDB = new Class_TicketDetailJob();
                        $resultClassDB['insert_ticketDetailJob'] = $classDB->insert($updateDataTicketDetailJob);  
                    }
                }
    
                if($dataLog!='') {
                    // delete ticket log
                    $requestClassDB = [];
                    $requestClassDB['id_ticket'] = $idTicket;
                   
                    $classDB = new Class_TicketLogs();
                    $resultClassDB['delete_ticketLog'] = $classDB->delete($requestClassDB);
                    
                    // insert ticket log
                    $insertDataTicketLog=[];
                    $insertDataTicketLog['id_ticket'] = $idTicket;
                    foreach($dataLog as $dataLog)
                    {
                        if (isset($dataLog['user_id'])) {$insertDataTicketLog['user_id'] = $dataLog['user_id'];}
                        if (isset($dataLog['name'])) {$insertDataTicketLog['name'] = $dataLog['name'];}
                        if (isset($dataLog['activity'])) {$insertDataTicketLog['activity'] = $dataLog['activity'];}
                        if (isset($dataLog['detail_act'])) {$insertDataTicketLog['detail_act'] = $dataLog['detail_act'];}
                        $classDB = new Class_TicketLogs();
                        $resultClassDB['insert_ticketLog'] = $classDB->insert($insertDataTicketLog);   
                    }
                }
    
                return $resultClassDB;
            }
        }
        catch(\Exception $e)
        {
            return response()->json(['status' => 'error','message' => $e]);
        }
    }
}
