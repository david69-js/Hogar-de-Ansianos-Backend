<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Una foto de un residente (perfil, álbum). El archivo vive en Cloudflare R2
 * (bucket privado); `full_url` genera una URL firmada temporal en vez de
 * exponer una URL pública fija, porque son fotos de personas, dato sensible.
 */
class ResidentImage extends Model
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
        if (!$this->image_path) {
            return null;
        }

        if (str_starts_with($this->image_path, 'http://') || str_starts_with($this->image_path, 'https://')) {
            return $this->image_path;
        }

        $disk = $this->storageDisk();

        // El bucket de R2 se mantiene privado (fotos de residentes = datos sensibles),
        // así que en vez de una URL pública fija se firma una temporal cada vez que se
        // sirve el registro. El disco local de desarrollo no soporta temporaryUrl().
        if ($disk === 'r2') {
            return Storage::disk($disk)->temporaryUrl($this->image_path, now()->addHour());
        }

        return Storage::disk($disk)->url($this->image_path);
    }
}
