<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistSchedule extends Model
{
    use HasFactory;

    protected $table = 'checklist_schedules';

    protected $appends = ['today_submission'];

    protected $fillable = [
        'checklist_master_id',
        'schedule_name',
        'periode_type',
        'schedule_details',
        'created_by',
        'end_date',
    ];

    /**
     * Cast schedule_details dari JSON ke array PHP secara otomatis.
     */
    protected $casts = [
        'schedule_details' => 'array',
    ];

    public function getTodaySubmissionAttribute()
    {
        return $this->attributes['today_submission'] ?? null;
    }

    /**
     * Sebuah jadwal dimiliki oleh satu master checklist.
     */
    public function master(): BelongsTo
    {
        return $this->belongsTo(ChecklistMaster::class, 'checklist_master_id');
    }

    /**
     * Sebuah jadwal dibuat oleh satu user.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}