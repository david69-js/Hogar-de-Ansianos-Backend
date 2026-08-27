<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationLog extends Model
{
    protected $guarded = ['id'];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MedicationSchedule::class, 'schedule_id');
    }

    public function administeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }
}
