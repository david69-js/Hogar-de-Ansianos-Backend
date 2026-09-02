<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila del registro de auditoría: quién hizo qué acción administrativa
 * (created/updated/deactivated/restored/deleted) sobre qué tabla y registro,
 * con los valores antes/después. Nadie la crea a mano ni vía API — la genera
 * únicamente App\Observers\AuditableObserver cuando se guarda/borra un modelo
 * observado. Por eso su controlador solo expone lectura (index/show).
 */
class AuditLog extends Model
{
    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
