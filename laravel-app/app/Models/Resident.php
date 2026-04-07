<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resident extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'dpi',
        'birth_date',
        'gender',
        'room_number',
        'admission_date',
        'blood_type',
        'weight',
        'height',
        'allergies',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'notes',
    ];
}
