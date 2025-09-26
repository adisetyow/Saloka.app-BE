<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\Import_TicketMst;

use Carbon\Carbon;
use DateTime;

class Ticket extends Controller
{
    /**Created Request Ticket
     */
    public function requestTicket($request)
    {
        try
        {
            DB::beginTransaction();
            // fill decclare variable reqeust
            $tittle=''; $user=''; $departemen=''; $type=''; $category=''; $status=''; $date=''; $descRequest='';$file=''; $urlPathFile=''; $pic=''; $assignee='';

            if (isset($request['tittle'])) {$tittle = $request['tittle'];}
            if (isset($request['departemen'])) {$departemen = $request['departemen'];}
            if (isset($request['type'])) {$type = $request['type'];}
            if (isset($request['category'])) {$category = $request['category'];}
            if (isset($request['status'])) {$status = $request['status'];}
            if (isset($request['date'])) {$date = $request['date'];}
            if (isset($request['desc_request'])) {$descRequest = $request['desc_request'];}
            if (isset($request['file'])) {$file = $request['file'];}
            if (isset($request['pic'])) {$pic = $request['pic'];}
            if (isset($request['assignee'])) {$assignee = $request['assignee'];}
    
            $result=[];
            /**Service API FROM LOKARYAWAN 
             * Get ID User Request From Service 
            */
            $requestAPIService=[];
            $requestAPIService['id_karyawan']=$pic;
            $APIService = new API_Service();
            $result['get_APIService'] = $APIService->getDataKaryawan($requestAPIService);
            $dataKaryawan = $result['get_APIService'];
            // Access the inner array
            $innerArray = $dataKaryawan[0];
            // Retrieve values by keys
       
            $idDepartemen = $innerArray['id_departemen'];
            $departemenRequest = $innerArray['departemen'];
            $subDepartemenRequest = $innerArray['sub_departemen'];
            $nip = $innerArray['username'];
            $nameRequest = $innerArray['name'];
            // #EndRegion

            $requestGenerateID=[];
            $classGenerateID = new Class_GenerateID(); 
            $idTicket = $classGenerateID->getIDTicket(); // GET ID Ticket
      
            // insert file
            if($request->file('file') !=null) {
                // call class upload Image
                $classUploadFile = new Class_UploadFile();
                $urlPathFile = $classUploadFile->uploadFile($request->file('file'),$idTicket);
            } 
    
            // Insert to Table Ticket MST
            $requestTransactionTicketMst = [];
            $requestTransactionTicketMst['id_ticket'] = $idTicket;
            $requestTransactionTicketMst['status'] = '1';
            $requestTransactionTicketMst['type'] = $type;
            $requestTransactionTicketMst['priority'] = '-';
            $requestTransactionTicketMst['category'] = $category;
            $requestTransactionTicketMst['subject'] = $tittle;
            $requestTransactionTicketMst['description'] = $descRequest;
            $requestTransactionTicketMst['file'] = $urlPathFile;
            $requestTransactionTicketMst['created_by'] = $nameRequest;
            $requestTransactionTicketMst['departemen'] = $departemen;
            $requestTransactionTicketMst['assignee'] = '-';
            $requestTransactionTicketMst['ticket_start'] = $date;
            $requestTransactionTicketMst['ticket_finish'] = null;
            $requestTransactionTicketMst['closing_by'] = '-';

            if($assignee!='')
            {
                $jsonListAssignee = json_decode($assignee);
                
                $assignee_='';
                foreach($jsonListAssignee as $v)
                {
                    $namaPenerima ='-';
                    $tanggalPengajuan = '';
                    $idKaryawan = $v->id_karyawan; 

                    /**Service API FROM LOKARYAWAN 
                    * Get ID User Request From Service 
                    */
                    $requestAPIService=[];
                    $requestAPIService['id_karyawan'] = $idKaryawan;
                    $APIService = new API_Service();
                    $result['get_APIService'] = $APIService->getDataKaryawan($requestAPIService);
                    $dataKaryawan = $result['get_APIService'];
                    // Access the inner array
                    $innerArray = $dataKaryawan[0];
                    // Retrieve values by keys
                    $name = $innerArray['name'];
                    $telephone = $innerArray['no_hp'];
                    // #EndRegion
                    $url=config('app.apiITDesk');
            
                    $message = "Notification From : IT-DESK"." \n\n".
                    "Dear Bapak/Ibu *".$name."* \n".
                    "Anda Mendapatkan Ticket Baru"." \n".
                    "ID Ticket : ". $idTicket ." \n\n".
                    "Subject : ".$tittle." \n\n".
                    "Description : \n*".$descRequest."* \n\n".
                    "File : \n".$url.'storage/ticket_master/'. $urlPathFile." \n\n".
                    "Departemen Request : \n*".$departemen."* \n\n".
                    "Link Aplikasi : https://salokapark.app/". " \n";
                
                    $requestSentWhatsapp=[];
                    $requestSentWhatsapp['message'] = $message;
                    $requestSentWhatsapp['telephone'] = $telephone;
                    $sentWhatsappController = new SentWhatsappController();
                    $result['sent_whatsapp'] = $sentWhatsappController->getServiceWhatsapp($requestSentWhatsapp);
                    $assignee_ = $name.','.$assignee_;
                }
                $requestTransactionTicketMst['assignee'] = $assignee_;
            }
          
            $classTransactionTicketMst = new Class_TicketMst();
            $result['insert_TransactionTicketMst'] = $classTransactionTicketMst->insert($requestTransactionTicketMst); 


            // insert history logs
            $requestWoLogs=[];
            $requestWoLogs['id_ticket'] = $idTicket;
            $requestWoLogs['user_id'] = $nip;
            $requestWoLogs['name'] = $nameRequest;
            $requestWoLogs['activity'] = 'Request';
            $requestWoLogs['detail_act'] = 'Create Ticket '. $tittle;

            $classWoLogs = new Class_TicketLogs();
            $result['insert_logs'] = $classWoLogs->insert($requestWoLogs);

            DB::commit();
            return $result;
        } catch (\Exception $ex) {
            DB::rollBack();
            return $ex;
        }
    }
    // #Endregion

    /**Update Request Ticket
     */
    public function editReqeustTicket($request)
    {
        try
        {
            DB::beginTransaction();

            $idTicket = $request['id_ticket'];
            $result=[];
            /**Service API FROM LOKARYAWAN 
             * Get ID User Request From Service 
            */
            $requestAPIService=[];
            $requestAPIService['id_karyawan']=$request['pic'];
            $APIService = new API_Service();
            $result['get_APIService'] = $APIService->getDataKaryawan($requestAPIService);
            $dataKaryawan = $result['get_APIService'];
            // Access the inner array
            $innerArray = $dataKaryawan[0];
            // Retrieve values by keys
       
            $idDepartemen = $innerArray['id_departemen'];
            $departemenRequest = $innerArray['departemen'];
            $subDepartemenRequest = $innerArray['sub_departemen'];
            $nip = $innerArray['username'];
            $nameRequest = $innerArray['name'];
            // #EndRegion
    
            // Insert to Table Ticket MST
            $requestTransactionTicketMst = []; 
            $assignee='';
            if (isset($request['id_ticket'])) {$requestTransactionTicketMst['id_ticket'] = $request['id_ticket'];}
            if (isset($request['tittle'])) {$requestTransactionTicketMst['subject'] = $request['tittle'];}
            if (isset($request['departemen'])) {$requestTransactionTicketMst['departemen'] = $request['departemen'];}
            if (isset($request['type'])) {$requestTransactionTicketMst['type'] = $request['type'];}
            if (isset($request['category'])) {$requestTransactionTicketMst['category'] = $request['category'];}
            if (isset($request['status'])) {$requestTransactionTicketMst['status'] = $request['status'];}
            if (isset($request['date'])) {$requestTransactionTicketMst['date'] = $request['date'];}
            if (isset($request['desc_request'])) {$requestTransactionTicketMst['description'] = $request['desc_request'];}
            if (isset($request['pic'])) {$requestTransactionTicketMst['pic'] = $request['pic'];}
            if (isset($request['assignee']) && $request['assignee']!='' ) {$assignee = $request['assignee'];}
            if (isset($request['priority'])) {$requestTransactionTicketMst['priority'] = $request['priority'];}
         
            $urlPathFile='';
            if($request->file('file') !=null) {
                // call class upload Image
                $classUploadFile = new Class_UploadFile();
                $urlPathFile = $classUploadFile->uploadFile($request->file('file'),$idTicket);

                if (isset($request['file'])) {$requestTransactionTicketMst['file'] = $urlPathFile;}
            } 
            if($assignee!='')
            {
                $jsonListAssignee = json_decode($assignee);
          
                $assignee_='';
                foreach($jsonListAssignee as $v)
                {
                    $namaPenerima ='-';
                    $tanggalPengajuan = '';
                    $idKaryawan = $v->id_karyawan; 

                    /**Service API FROM LOKARYAWAN 
                    * Get ID User Request From Service 
                    */
                    $requestAPIService=[];
                    $requestAPIService['id_karyawan'] = $idKaryawan;
                    $APIService = new API_Service();
                    $result['get_APIService'] = $APIService->getDataKaryawan($requestAPIService);
                    $dataKaryawan = $result['get_APIService'];
                    // Access the inner array
                    $innerArray = $dataKaryawan[0];
                    // Retrieve values by keys
                    $name = $innerArray['name'];
                    $telephone = $innerArray['no_hp'];
                    // #EndRegion
                    $url=config('app.apiITDesk');
                 
                    $message = "Notification From : IT-DESK"." \n\n".
                    "Dear Bapak/Ibu *".$name."* \n".
                    "Anda Mendapatkan Ticket Baru"." \n".
                    "ID Ticket : ". $idTicket ." \n\n".
                    "Subject : ".$request['tittle']." \n\n".
                    "Description : \n*".$request['desc_request']."* \n\n".
                    "File : \n".$url.'storage/ticket_master/'. $urlPathFile." \n\n".
                    "Departemen Request : \n*".$request['departemen']."* \n\n".
                    "Link Aplikasi : https://salokapark.app/". " \n";
                
                    $requestSentWhatsapp=[];
                    $requestSentWhatsapp['message'] = $message;
                    $requestSentWhatsapp['telephone'] = $telephone;
                    $sentWhatsappController = new SentWhatsappController();
                    $result['sent_whatsapp'] = $sentWhatsappController->getServiceWhatsapp($requestSentWhatsapp);
                    $assignee_ = $name.','.$assignee_;
                }
                $requestTransactionTicketMst['assignee'] = $assignee_;
            }

            if (isset($request['ticket_start'])) {$requestTransactionTicketMst['ticket_start'] = $request['ticket_start'];}
            if (isset($request['ticket_finish'])) {$requestTransactionTicketMst['ticket_finish'] = $request['ticket_finish'];}
            if (isset($request['closing_by'])) {$requestTransactionTicketMst['closing_by'] = $request['closing_by'];}
        
            $classTransactionTicketMst = new Class_TicketMst();
            $result['update_TransactionTicketMst'] = $classTransactionTicketMst->update($requestTransactionTicketMst); 
        
            // insert history logs
            $requestWoLogs=[];
            $requestWoLogs['id_ticket'] = $idTicket;
            $requestWoLogs['user_id'] = $nip;
            $requestWoLogs['name'] = $nameRequest;
            $requestWoLogs['activity'] = 'Update';
            $requestWoLogs['detail_act'] = 'Edit Ticket ID : ' . $idTicket;

            $classWoLogs = new Class_TicketLogs();
            $result['insert_logs'] = $classWoLogs->insert($requestWoLogs);

            DB::commit();
            return $result;
        } catch (\Exception $ex) {
            DB::rollBack();
            return $ex;
        }
    }
    // #Endregion

    /**Add Job Ticket
     */
    public function addJobTicket($request)
    {
        try
        {
            DB::beginTransaction();
            // declare variable
            $idTicket = $request['id_ticket'];
            $result=[];
            /**Service API FROM LOKARYAWAN 
             * Get ID User Request From Service 
            */
            $requestAPIService=[];
            $requestAPIService['id_karyawan']=$request['pic'];
            $APIService = new API_Service();
            $result['get_APIService'] = $APIService->getDataKaryawan($requestAPIService);
            $dataKaryawan = $result['get_APIService'];
            // Access the inner array
            $innerArray = $dataKaryawan[0];
            // Retrieve values by keys
       
            $idDepartemen = $innerArray['id_departemen'];
            $departemenRequest = $innerArray['departemen'];
            $subDepartemenRequest = $innerArray['sub_departemen'];
            $nip = $innerArray['username'];
            $nameRequest = $innerArray['name'];
            // #EndRegion
    
            // Insert to Table Ticket Detail Job
            $requestDetailJobTicket = [];
            $requestDetailJobTicket['id_ticket'] = $idTicket;
            $requestDetailJobTicket['subject'] = $request['subject'];
            $requestDetailJobTicket['detail_job'] = $request['detail_job'];
            
            $randomNumber = rand(1000, 9999);
            if($request->file('file') !=null) {
                // call class upload Image
                $classUploadFile = new Class_UploadFile();
                $urlPathFile = $classUploadFile->uploadFile($request->file('file'),$idTicket.'-'.$randomNumber);

                $requestDetailJobTicket['file'] = $urlPathFile;
            } 
            $requestDetailJobTicket['pic_name'] = $nameRequest;
 
            $classTicketDetailJob = new Class_TicketDetailJob();
            $result['add_TicketDetailJob'] = $classTicketDetailJob->insert($requestDetailJobTicket); 
        
            // insert history logs
            $requestWoLogs=[];
            $requestWoLogs['id_ticket'] = $idTicket;
            $requestWoLogs['user_id'] = $nip;
            $requestWoLogs['name'] = $nameRequest;
            $requestWoLogs['activity'] = 'Add Detail Job';
            $requestWoLogs['detail_act'] = 'Menambahkan Detail Job Ticket ID : ' . $idTicket;

            $classWoLogs = new Class_TicketLogs();
            $result['insert_logs'] = $classWoLogs->insert($requestWoLogs);

            DB::commit();
            return $result;
        } catch (\Exception $ex) {
            DB::rollBack();
            return $ex;
        }
    }
    // #Endregion

    /**Update Job Ticket
     */
    public function editJobTicket($request)
    {
        try
        {
            DB::beginTransaction();
      
            $result=[];
            /**Service API FROM LOKARYAWAN 
             * Get ID User Request From Service 
            */
            $requestAPIService=[];
            $requestAPIService['id_karyawan']=$request['pic'];
            $APIService = new API_Service();
            $result['get_APIService'] = $APIService->getDataKaryawan($requestAPIService);
            $dataKaryawan = $result['get_APIService'];
            // Access the inner array
            $innerArray = $dataKaryawan[0];
            // Retrieve values by keys
       
            $idDepartemen = $innerArray['id_departemen'];
            $departemenRequest = $innerArray['departemen'];
            $subDepartemenRequest = $innerArray['sub_departemen'];
            $nip = $innerArray['username'];
            $nameRequest = $innerArray['name'];
            // #EndRegion
    
            // Insert to Table Ticket Detail Job
            $requestDetailJobTicket = [];
            $requestDetailJobTicket['id'] = $request['id'];
            $requestDetailJobTicket['id_ticket'] = $request['id_ticket'];
            $requestDetailJobTicket['subject'] = $request['subject'];
            $requestDetailJobTicket['detail_job'] = $request['detail_job'];

            $randomNumber = rand(1000, 9999);
            if($request->file('file') !=null) {
                // call class upload Image
                $classUploadFile = new Class_UploadFile();
                $urlPathFile = $classUploadFile->uploadFile($request->file('file'),$request['id_ticket'].'-'.$randomNumber);

                $requestDetailJobTicket['file'] = $urlPathFile;
            } 
            $requestDetailJobTicket['pic_name'] = $nameRequest;

            $classTicketDetailJob = new Class_TicketDetailJob();
            $result['update_TicketDetailJob'] = $classTicketDetailJob->update($requestDetailJobTicket); 
        
            // insert history logs
            $requestWoLogs=[];
            $requestWoLogs['id_ticket'] = $request['id_ticket'];
            $requestWoLogs['user_id'] = $nip;
            $requestWoLogs['name'] = $nameRequest;
            $requestWoLogs['activity'] = 'Edit Detail Job';
            $requestWoLogs['detail_act'] = 'Update Detail Job Ticket ID : ' . $request['id_ticket'];

            $classWoLogs = new Class_TicketLogs();
            $result['insert_logs'] = $classWoLogs->insert($requestWoLogs);

            DB::commit();
            return $result;
        } catch (\Exception $ex) {
            DB::rollBack();
            return $ex;
        }
    }
    // #Endregion

    /**Update Request Ticket
     */
    public function declareCloseTicket($request)
    {
        try
        {
            DB::beginTransaction();
      
            $idTicket = $request['id_ticket'];
            $result=[];
            /**Service API FROM LOKARYAWAN 
             * Get ID User Request From Service 
            */
            $requestAPIService=[];
            $requestAPIService['id_karyawan']=$request['closing_by'];
            $APIService = new API_Service();
            $result['get_APIService'] = $APIService->getDataKaryawan($requestAPIService);
            $dataKaryawan = $result['get_APIService'];
            // Access the inner array
            $innerArray = $dataKaryawan[0];
            // Retrieve values by keys
       
            $idDepartemen = $innerArray['id_departemen'];
            $departemenRequest = $innerArray['departemen'];
            $subDepartemenRequest = $innerArray['sub_departemen'];
            $nip = $innerArray['username'];
            $nameRequest = $innerArray['name'];
            // #EndRegion

            // get interval time solved
            // get ticket start
            $requestTransactionTicketMst = [];
            $requestTransactionTicketMst['id_ticket'] = $idTicket;
            $classTransactionTicketMst = new Class_TicketMst();
            $result['get_TransactionTicketMst'] = $classTransactionTicketMst->show($requestTransactionTicketMst); 
            $ticketStart = $result['get_TransactionTicketMst'][0]->ticket_start;
            
            $startDateTime = Carbon::parse($ticketStart);
            $endDateTime = Carbon::now()->format('Y-m-d H:i:s');

            // Calculate the difference
            $intervalSolved = $startDateTime->diff($endDateTime);
            $time_solved = $intervalSolved->format('%d days, %h hours, %i minutes');
         
            // Insert to Table Ticket MST
            $requestTransactionTicketMst = [];
            $requestTransactionTicketMst['id_ticket'] = $idTicket;
            $requestTransactionTicketMst['status'] = '9';
            $requestTransactionTicketMst['ticket_finish'] = Carbon::now()->format('Y-m-d H:i:s');
            $requestTransactionTicketMst['time_solved'] = $time_solved;
            $requestTransactionTicketMst['closing_by'] = $nameRequest;
            if (isset($request['description_trouble'])) {$requestTransactionTicketMst['description_trouble'] = $request['description_trouble'];}
            if (isset($request['description_solution'])) {$requestTransactionTicketMst['description_solution'] = $request['description_solution'];}

            $classTransactionTicketMst = new Class_TicketMst();
            $result['update_TransactionTicketMst'] = $classTransactionTicketMst->update($requestTransactionTicketMst); 
        
            // insert history logs
            $requestWoLogs=[];
            $requestWoLogs['id_ticket'] = $idTicket;
            $requestWoLogs['user_id'] = $nip;
            $requestWoLogs['name'] = $nameRequest;
            $requestWoLogs['activity'] = 'Declare to Close';
            $requestWoLogs['detail_act'] = 'Close Ticket ID : ' . $idTicket;

            $classWoLogs = new Class_TicketLogs();
            $result['insert_logs'] = $classWoLogs->insert($requestWoLogs);

            DB::commit();
            return $result;
        } catch (\Exception $ex) {
            DB::rollBack();
            return $ex;
        }
    }
    // #Endregion

    /**Get Request Ticket
     */
    public function getRequestTicket($request)
    {
        try
        {
            $idTicket='';
            if (isset($request['id_ticket'])) {$idTicket = $request['id_ticket'];}

            $result=[];

            $classTicketMst = new Class_TicketMst();
            $result['ticket_mst'] = $classTicketMst->show($request);

            if($idTicket !='')
            {
                $requestDetailJob =[];
                $requestDetailJob['id_ticket']= $idTicket;

                $classTicketDetailJob = new Class_TicketDetailJob();
                $result['detial_job'] = $classTicketDetailJob->show($requestDetailJob);
    
                $requestLogs =[];
                $requestLogs['id_ticket']= $idTicket;

                $classTicketLogs = New Class_TicketLogs();
                $result['logs'] = $classTicketLogs->show($requestLogs);
            }

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }
    }
    // #Endregion

    /**Import Ticket From Excel
    */
    public function importTicketFromExcel(Request $request) 
    {
        try
        {
            // validasi
            $this->validate($request, [
                'file' => 'required|mimes:csv,xls,xlsx'
            ]);
        
            // menangkap file excel
            $file = $request->file('file');

            Excel::import(new Import_TicketMst,$file);
            return 'success';
        } catch (\Exception $ex) {
            return response()->json([$ex]);
        }
    }

    private function getAssigneeName($request)
    {
        try
        {
            $jsonListAssignee = json_decode($assignee);
          
            $assignee_='';
            foreach($jsonListAssignee as $v)
            {
                $namaPenerima ='-';
                $tanggalPengajuan = '';
                $idKaryawan = $v->id_karyawan; 

                /**Service API FROM LOKARYAWAN 
                * Get ID User Request From Service 
                */
                $requestAPIService=[];
                $requestAPIService['id_karyawan'] = $idKaryawan;
                $APIService = new API_Service();
                $result['get_APIService'] = $APIService->getDataKaryawan($requestAPIService);
                $dataKaryawan = $result['get_APIService'];
                // Access the inner array
                $innerArray = $dataKaryawan[0];
                // Retrieve values by keys
                $name = $innerArray['name'];
                $telephone = $innerArray['no_hp'];
                // #EndRegion
                $url=config('app.apiITDesk');
             
                $message = "Notification From : IT-DESK"." \n\n".
                "Dear Bapak/Ibu *".$name."* \n".
                "Anda Mendapatkan Ticket Baru"." \n".
                "ID Ticket : ". $idTicket ." \n\n".
                "Subject : ".$request['tittle']." \n\n".
                "Description : \n*".$request['desc_request']."* \n\n".
                "File : \n".$url.'storage/ticket_master/'. $urlPathFile." \n\n".
                "Departemen Request : \n*".$request['departemen']."* \n\n".
                "Link Aplikasi : https://salokapark.app/". " \n";
            
                $requestSentWhatsapp=[];
                $requestSentWhatsapp['message'] = $message;
                $requestSentWhatsapp['telephone'] = $telephone;
                $sentWhatsappController = new SentWhatsappController();
                $result['sent_whatsapp'] = $sentWhatsappController->getServiceWhatsapp($requestSentWhatsapp);
                $assignee_ = $name.','.$assignee_;
            }
            $requestTransactionTicketMst['assignee'] = $assignee_;
            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }
    }


}
