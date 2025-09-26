<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistSubmissionDetail extends Model
{
    use HasFactory;

    protected $table = 'checklist_submission_details';

    /**
     * Laravel tidak mengelola timestamps (created_at, updated_at) untuk tabel ini.
     */
    public $timestamps = false;

    protected $fillable = [
        'submission_id',
        'item_id',
        'is_checked',
        'notes',
    ];

    /**
     * Cast is_checked menjadi tipe data boolean.
     */
    protected $casts = [
        'is_checked' => 'boolean',
    ];

    /**
     * Sebuah detail dimiliki oleh satu submission.
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(ChecklistSubmission::class, 'submission_id');
    }

    /**
     * Sebuah detail merujuk pada satu item checklist.
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'item_id');
    }
}