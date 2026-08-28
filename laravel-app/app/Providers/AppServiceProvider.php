<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Provider por defecto de Laravel, sin uso propio en este proyecto: no registra
 * bindings ni bootstrapea nada personalizado. Los roles/permisos los provee el
 * propio ServiceProvider del paquete Spatie (spatie/laravel-permission), y la
 * auditoría de modelos se activa por observer en cada modelo (ver
 * app/Observers/AuditableObserver.php), no aquí.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
