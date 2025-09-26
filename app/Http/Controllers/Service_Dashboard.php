<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use DateTime;

class Service_Dashboard extends Controller
{
    public function getDashboard(Request $request)
    {
        try
        {
            $requestModel=[];
            $period =''; $dateStart=''; $dateEnd='';
            if (isset($request['period'])) {$period = $request['period'];}
            if (isset($request['date_start'])) {$requestModel['date_start'] = $request['date_start'];}
            if (isset($request['date_finish'])) {$requestModel['date_finish'] = $request['date_finish'];}

            if($period!='')
            {
                // cek type period
                if($period=='1') // 1 minggu terkahir
                {
                    // Mendapatkan tanggal sekarang
                    $endDate = Carbon::now();

                    // Mendapatkan tanggal 1 minggu yang lalu dari hari ini
                    $startDate = Carbon::now()->subWeek();

                    // Mengubah format tanggal jika diperlukan
                    $startDateFormatted = $startDate->format('Y-m-d');
                    $endDateFormatted = $endDate->format('Y-m-d');
                }
                if($period=='2') // 2 minggu terakhir
                {
                    // Mendapatkan tanggal sekarang
                    $endDate = Carbon::now();

                    // Mendapatkan tanggal 2 minggu yang lalu dari hari ini
                    $startDate = Carbon::now()->subWeek(2);

                    // Mengubah format tanggal jika diperlukan
                    $startDateFormatted = $startDate->format('Y-m-d');
                    $endDateFormatted = $endDate->format('Y-m-d');
                }
                if($period=='3') // 1 bulan terakhir
                {
                    // Mendapatkan tanggal sekarang
                    $endDate = Carbon::now();

                    // Mendapatkan tanggal 1 bulan yang lalu dari hari ini (awal)
                    $startDate = Carbon::now()->subMonth();

                    // Mengubah format tanggal jika diperlukan
                    $startDateFormatted = $startDate->format('Y-m-d');
                    $endDateFormatted = $endDate->format('Y-m-d');
                }
                if($period=='4') // 3 bulan terkahir
                {
                    // Mendapatkan tanggal sekarang
                    $endDate = Carbon::now();

                    // Mendapatkan tanggal 3 bulan yang lalu dari hari ini (awal)
                    $startDate = Carbon::now()->subMonth(3);

                    // Mengubah format tanggal jika diperlukan
                    $startDateFormatted = $startDate->format('Y-m-d');
                    $endDateFormatted = $endDate->format('Y-m-d');
                }
                if($period=='5') // 6 bulan terakhir
                {
                    // Mendapatkan tanggal sekarang
                    $endDate = Carbon::now();

                    // Mendapatkan tanggal 6 bulan yang lalu dari hari ini (awal)
                    $startDate = Carbon::now()->subMonth(6);

                    // Mengubah format tanggal jika diperlukan
                    $startDateFormatted = $startDate->format('Y-m-d');
                    $endDateFormatted = $endDate->format('Y-m-d');
                }
                if($period=='6') // 1 tahun
                {
                    // Mendapatkan tanggal sekarang
                    $endDate = Carbon::now();

                    // Mendapatkan tanggal 1 tahun yang lalu dari hari ini (awal)
                    $startDate = Carbon::now()->subYear();

                    // Mengubah format tanggal jika diperlukan
                    $startDateFormatted = $startDate->format('Y-m-d');
                    $endDateFormatted = $endDate->format('Y-m-d');
                }
            }
            $requestModel['ticket_start'] = $startDateFormatted;
            $requestModel['ticket_finish'] = $endDateFormatted;

            $model = new Dashboard();
            $resultModel['Ticket_Total'] = $model->getTotTicket($requestModel); 

            $model = new Dashboard();
            $resultModel['Ticket_Open'] = $model->getTotTicketOpen($requestModel); 

            $model = new Dashboard();
            $resultModel['Ticket_Close'] = $model->getTotTicketClose($requestModel); 
            
            $model = new Dashboard();
            $resultModel['Top_Departemen'] = $model->getTopDepartemen($requestModel); 

            $model = new Dashboard();
            $resultModel['Top_Category'] = $model->getTopCategory($requestModel); 
            
            $result=response()->json([
                'status' => 'success',
                'message' => 'Get Dashboard Successfuly',
                'data' => $resultModel
            ]);

            return $result;
        } catch (\Exception $ex) {
            return $ex;
        }  
    }
}
