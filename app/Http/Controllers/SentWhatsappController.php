<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SentWhatsappController extends Controller
{
     // server WA
     public function getServiceWhatsapp($request)
     {
         $telephone = $request['telephone'];
         $message = $request['message'];
         
         try 
         {
             $client = new \GuzzleHttp\Client();
             $urlServerLokaHR = config('app.apiFontee');
         
             $response = $client->request('POST', $urlServerLokaHR.'send', [
                 'headers' => [
                     'Authorization' => '+PkfUaYYGfR1+gRCx9no',
                     'Content-Type' => 'application/json',
                 ],
                 'json' => [
                     'target' => $telephone,
                     'message' => $message
                 ],
             ]);
             $data = json_decode($response->getBody(), true);
             return $data;
         } catch (\Exception $ex) {
            dd($ex);
             return $ex;
         }
     }
}
