<?php

namespace App\Models;

use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Catálogo de medicamentos + inventario simple (un stock por medicamento, no
 * por lote). `stock_quantity`/`expiration_date` nunca se editan directo desde
 * el formulario del catálogo — solo cambian a través de un movimiento en
 * MedicationStockMovement, para que quede rastro auditable de cada cambio de
 * stock (ver MedicationController::store/update, que explícitamente no los
 * acepta). Un medicamento en uso en alguna prescripción no puede eliminarse.
 */
class Medication extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::observe(AuditableObserver::class);
    }

    protected $guarded = ['id'];

    protected $appends = [
        'image_url',
    ];

    /**
     * URL de la foto del medicamento, o null si no tiene.
     *
     * Mismo criterio que en ResidentImage y User: el bucket de R2 es privado, así
     * que se firma una URL temporal en vez de exponer una pública fija. El disco
     * local de desarrollo no soporta temporaryUrl().
     */
    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }

        if (str_starts_with($this->image, 'http://') || str_starts_with($this->image, 'https://')) {
            return $this->image;
        }

        $disk = config('filesystems.default') === 'r2' ? 'r2' : 'public';

        if ($disk === 'r2') {
            return Storage::disk($disk)->temporaryUrl($this->image, now()->addHour());
        }

        return Storage::disk($disk)->url($this->image);
    }
}
