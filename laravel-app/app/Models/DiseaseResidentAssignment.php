<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiseaseResidentAssignment extends Model
{
    protected $guarded = ['id'];

    // La tabla no tiene columnas created_at/updated_at (ver migración).
    public $timestamps = false;
}
