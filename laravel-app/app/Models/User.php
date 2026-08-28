<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Observers\AuditableObserver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

/**
 * Un miembro del personal (Admin, Enfermera o Staff — no un residente). El rol
 * real de autorización viene del paquete Spatie (HasRoles: roles/permisos),
 * no de la columna `role` en texto (esa es solo informativa/legacy). Baja
 * lógica: `status` = "inactive" bloquea el login (ver AuthController::login),
 * independiente de `deleted_at` (SoftDeletes). Cada create/update/delete queda
 * en el registro de auditoría vía AuditableObserver.
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::observe(AuditableObserver::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'profile_image_url',
    ];

    public function getProfileImageUrlAttribute(): ?string
    {
        if (!$this->profile_image) {
            return null;
        }

        if (str_starts_with($this->profile_image, 'http://') || str_starts_with($this->profile_image, 'https://')) {
            return $this->profile_image;
        }

        $disk = config('filesystems.default') === 'r2' ? 'r2' : 'public';

        // Igual que en ResidentImage: bucket privado, URL firmada con expiración
        // en vez de una URL pública fija. El disco local de dev no la soporta.
        if ($disk === 'r2') {
            return Storage::disk($disk)->temporaryUrl($this->profile_image, now()->addHour());
        }

        return Storage::disk($disk)->url($this->profile_image);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->second_last_name,
        ])));
    }
}
