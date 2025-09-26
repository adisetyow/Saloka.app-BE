<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    use HasFactory;

    protected $table = 'checklist_items';

    protected $fillable = [
        'checklist_master_id',
        'activity_name',
        'order',
        'is_required',
    ];

    /**
     * Sebuah item dimiliki oleh satu master checklist.
     */
    public function master(): BelongsTo
    {
        return $this->belongsTo(ChecklistMaster::class, 'checklist_master_id');
    }
}