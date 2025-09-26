<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class Class_InventoryDevice extends Controller
{
    /**
     * Read table
     */
    public function show($request)
    {
        // set value variable
        $category = ''; $location = ''; $sn=''; $wlan=''; $condition=''; $validation=''; $date=''; $users=''; $model=''; $os=''; $equipment=''; $status=''; $note='';

        // declare variable set
        if (isset($request['category'])) {$category = $request['category'];}
        if (isset($request['location'])) {$location = $request['location'];}
        if (isset($request['sn'])) {$sn = $request['sn'];}
        if (isset($request['wlan'])) {$wlan = $request['wlan'];}
        if (isset($request['condition'])) {$condition = $request['condition'];}
        if (isset($request['validation'])) {$validation = $request['validation'];}
        if (isset($request['date'])) {$date = $request['date'];}
        if (isset($request['users'])) {$users = $request['users'];}
        if (isset($request['model'])) {$model = $request['model'];}
        if (isset($request['os'])) {$os = $request['os'];}
        if (isset($request['equipment'])) {$equipment = $request['equipment'];}
        if (isset($request['status'])) {$status = $request['status'];}
        if (isset($request['note'])) {$note = $request['note'];}

        try
        {
            $data_ = DB::table('inventory_device');

            if($category!='')
            {
                $data_->where('category', $category);
            }
            if($location!='')
            {
                $data_->where('location', $location);
            }
            if($sn!='')
            {
                $data_->where('sn', $sn);
            }
            if($wlan!='')
            {
                $data_->where('wlan', $wlan);
            }
            if($condition!='')
            {
                $data_->where('dondition', $condition);
            }
            if($validation!='')
            {
                $data_->where('validation', $validation);
            }
            if($date!='')
            {
                $data_->where('date', $date);
            }
            if($users!='')
            {
                $data_->where('users', $users);
            }
            if($model!='')
            {
                $data_->where('model', $model);
            }
            if($os!='')
            {
                $data_->where('os', $os);
            }
            if($equipment!='')
            {
                $data_->where('equipment', $equipment);
            }
            if($status!='')
            {
                $data_->where('status', $status);
            }
            if($note!='')
            {
                $data_->where('note', $note);
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