<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Models\inventory_category;
use Carbon\Carbon;
use DateTime;


class Class_InventoryCategory extends Controller
{
    /**
     * Read table
     */
    public function show($request)
    {
        // set value variable
        $category = '';
        $isActive = '';

        // declare variable set
        if (isset($request['category'])) {$category = $request['category'];}
        if (isset($request['is_active'])) {$isActive = $request['is_active'];}

        try
        {
            $data_ = DB::table('inventory_category');

            if($category!='')
            {
                $data_->where('category','like','%'.$category.'%');
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
        $category = '';
        $isActive = '1';
        
        // declare variable set
        if (isset($request['category'])) {$category = $request['category'];}
        if (isset($request['is_active'])) {$isActive = $request['is_active'];}
        
        try
        {
            // cek data
            $request=[];
            $request['category'] = $category;
            $request['is_active'] = $isActive;

            $dataTransaction = $this->show($request);
     
            if(isset($dataTransaction))
            {
                // data sudah ada
                return 'double data';
            }
            else
            {
                $data = new inventory_category();
                $data->category = $category;
                $data->is_active = $isActive; 
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
        $id=''; 
        $updateData =[];
        try
        {
            // declare variable set
            if (isset($request['id'])) {$id = $request['id'];}
            if (isset($request['category'])) {$updateData['category'] = $request['category'];}
            if (isset($request['is_active']) && $request['is_active']!='') {$updateData['is_active'] = $request['is_active'];}

            DB::table('inventory_category')
            ->where('id','=',$id)
            ->update($updateData);
   
            return 'success';
        } catch (\Exception $ex) {
            return $ex;
        }
    }
}