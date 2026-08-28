<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Model;

class DiseaseResidentAssignment extends Model
{
    protected static function booted(): void
    {
        static::observe(AuditableObserver::class);
    }

    protected $guarded = ['id'];

    // La tabla no tiene columnas created_at/updated_at (ver migración).
    public $timestamps = false;
}
