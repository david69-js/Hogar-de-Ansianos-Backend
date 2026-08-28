<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Token de Firebase Cloud Messaging de UN dispositivo/navegador concreto, no de
 * una sesión — por eso el token es único por fila y sobrevive a un logout normal.
 * Un mismo usuario puede tener varias filas (celular + navegador). El backend le
 * manda pushes a través de FirebaseService (ver CheckPendingMedications y
 * CheckMedicationStock). AuthContext.signOut() en el frontend debe des-registrar
 * (DELETE) el token de este dispositivo al cerrar sesión, para que deje de
 * recibir avisos mientras nadie esté logueado en él.
 */
class DeviceToken extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
