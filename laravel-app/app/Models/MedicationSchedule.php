<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MedicationSchedule extends Model
{
    protected $guarded = ['id'];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MedicationLog::class, 'schedule_id');
    }
}
