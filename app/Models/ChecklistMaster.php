<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChecklistMaster extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'checklist_masters';

    protected $fillable = [
        'checklist_id',
        'name',
        'checklist_type_id',
        'created_by',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::deleting(function ($checklistMaster) {
            // Hapus semua jadwal yang terkait dengan master ini
            $checklistMaster->schedules()->delete();
        });
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ChecklistType::class, 'checklist_type_id');
    }

    /**
     * Sebuah master checklist memiliki banyak item.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class);
    }

    /**
     * Sebuah master checklist memiliki banyak jadwal.
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(ChecklistSchedule::class);
    }

    /**
     * Sebuah master checklist dibuat oleh satu user.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}