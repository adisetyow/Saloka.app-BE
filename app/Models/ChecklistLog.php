<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChecklistLog extends Model
{
    use HasFactory;

    protected $table = 'checklist_logs';

    protected $fillable = [
        'checklist_master_id',
        'user_id',
        'name',
        'activity',
        'detail_act',
    ];
}