<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use DateTime;

class Class_UploadFile extends Controller
{
    public function uploadFile(UploadedFile $image,$idTicket)
    {
        try
        {
         
            // cek if pdf
            if($image->getClientOriginalExtension() =='pdf')
            {   
                $imageName = 'PDF_REQUEST_'.$idTicket.'.'. $image->getClientOriginalExtension();
                $path = 'ticket_master' . '/' . $imageName;

                // Save the PDF to the public storage path
                $image->move(public_path('storage/ticket_master'), $imageName);
                
            }
            else
            {
                $imageName = 'IMG_REQUEST_'.$idTicket.'.'. $image->getClientOriginalExtension();
                
                $image = Image::make($image)->resize(800, null, function ($constraint) {
                    $constraint->aspectRatio();
                });

                $path = 'ticket_master' . '/' . $imageName;
                $image->save(public_path('storage' . DIRECTORY_SEPARATOR . $path));   
            }
           
            return  $imageName;
        } catch (\Exception $ex) {
            dd($ex);
            return $ex;
        }
    }
}
