<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistSubmission extends Model
{
    use HasFactory;

    protected $table = 'checklist_submissions';

    protected $fillable = [
        'checklist_schedule_id',
        'submission_date',
        'submitted_by',
        'status',
        'notes',
    ];

    /**
     * Sebuah submission memiliki banyak detail item.
     */
    public function details(): HasMany
    {
        return $this->hasMany(ChecklistSubmissionDetail::class, 'submission_id');
    }

    /**
     * Sebuah submission dimiliki oleh satu jadwal.
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ChecklistSchedule::class, 'checklist_schedule_id');
    }

    /**
     * Sebuah submission dikerjakan oleh satu user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}