<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Un archivo del expediente médico previo de un residente (PDF, Word, imagen
 * de documento escaneado) — a diferencia de ResidentImage (siempre foto) y
 * ResidentReport (siempre texto), este acepta cualquier tipo de archivo.
 * Igual que ResidentImage, vive en Cloudflare R2 y se sirve con URL firmada
 * temporal, no una URL pública fija.
 */
class ResidentDocument extends Model
{
    protected $guarded = ['id'];

    protected $appends = [
        'full_url',
    ];

    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    private function storageDisk(): string
    {
        return config('filesystems.default') === 'r2' ? 'r2' : 'public';
    }

    public function getFullUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        $disk = $this->storageDisk();

        // Igual que ResidentImage: bucket privado, URL firmada con expiración en vez
        // de una pública fija. El disco local de desarrollo no soporta temporaryUrl().
        if ($disk === 'r2') {
            return Storage::disk($disk)->temporaryUrl($this->file_path, now()->addHour());
        }

        return Storage::disk($disk)->url($this->file_path);
    }
}
