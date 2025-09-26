<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client;

class API_Service extends Controller
{
    public function getDataKaryawan($request)
    {
        $idKaryawan = $request['id_karyawan'];

        // $fakeUserData = [
        //     [
        //         "id_karyawan" => $idKaryawan,
        //         // "kaaryawan_id" => $karyawan_id ?? null,
        //         "name" => "User Lokal (ID: " . $idKaryawan . ")",
        //         "email" => "user." . $idKaryawan . "@testing.com",

        //         // Tambahkan field lain jika dibutuhkan oleh kode Anda
        //     ]
        // ];

        // return $fakeUserData;


        //KODE ASLI//

        try {
            // $result = [];
            $urlServerLokaHR = config('app.apiLokaHR');
            $client = new Client();
            $response = $client->request('GET', $urlServerLokaHR . 'get_karyawan_byID', [
                'json' => [
                    'id_karyawan' => $idKaryawan,
                ]
            ]);
            $jsonData = json_decode($response->getBody(), true);

            // if ($jsonData['status'] == 'success' && $jsonData['data'] != null) {
            //     $result = $jsonData['data'];
            if (isset($jsonData['status']) && $jsonData['status'] == 'success' && !empty($jsonData['data'])) {
                return $jsonData['data'];
            }
            return ['status' => 'error', 'message' => 'API LokaHR: Karyawan tidak ditemukan atau respons gagal.'];

        } catch (\GuzzleHttp\Exception\ConnectException $ex) {

            return ['status' => 'error', 'message' => 'Gagal terhubung ke server API LokaHR. Periksa URL atau koneksi jaringan.'];
        } catch (\Exception $ex) {

            return ['status' => 'error', 'message' => $ex->getMessage()];
        }
    }
}
