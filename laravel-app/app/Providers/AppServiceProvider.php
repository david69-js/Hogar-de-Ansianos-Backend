<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

/**
 * Registra los límites de intentos de las rutas públicas (ver boot()).
 *
 * Los roles/permisos los provee el ServiceProvider del paquete Spatie
 * (spatie/laravel-permission) y la auditoría de modelos se activa por observer
 * en cada modelo (app/Observers/AuditableObserver.php); nada de eso pasa por acá.
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
        $this->registerRateLimiters();
    }

    /**
     * Límites de intentos para las tres rutas que no piden token.
     *
     * Sin esto, /login aceptaba intentos ilimitados (medido: 12 contraseñas
     * incorrectas seguidas, ninguna respuesta 429) y /password/forgot se podía
     * disparar en bucle, mandando un correo por cada llamada.
     *
     * Cada límite se cuenta por cuenta + dirección IP, no solo por IP, porque en
     * el hogar todas las computadoras salen por la misma conexión: si se contara
     * solo por IP, una enfermera equivocándose de contraseña dejaría a sus
     * compañeras sin poder entrar. El segundo límite, más amplio y sí por IP,
     * es el que frena un ataque automatizado.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return [
                Limit::perMinute(5)->by($this->accountKey($request)),
                Limit::perMinute(30)->by($request->ip()),
                // Tercer límite, solo por cuenta: la IP llega en una cabecera que
                // pone el proxy (ver trustProxies en bootstrap/app.php) y que un
                // atacante puede falsificar, rotándola para esquivar los dos
                // límites de arriba. Este no depende de la IP, así que pone un
                // techo real a los intentos contra una misma cuenta. Veinte por
                // hora no molesta a nadie que de verdad esté entrando a trabajar.
                Limit::perHour(20)->by('login-account:' . Str::lower((string) $request->input('email'))),
            ];
        });

        // Enviar el código de recuperación cuesta un correo real (Resend), así
        // que es más estricto que iniciar sesión.
        RateLimiter::for('password-forgot', function (Request $request) {
            return [
                Limit::perMinutes(10, 3)->by($this->accountKey($request)),
                Limit::perMinutes(10, 10)->by($request->ip()),
            ];
        });

        // PasswordResetController ya corta a los N intentos fallidos del MISMO
        // código; esto cubre el hueco de al lado: pedir código nuevo y volver a
        // probar, una y otra vez.
        RateLimiter::for('password-reset', function (Request $request) {
            return [
                Limit::perMinutes(10, 6)->by($this->accountKey($request)),
                Limit::perMinutes(10, 20)->by($request->ip()),
            ];
        });
    }

    /** Clave "cuenta + IP": el correo va en minúsculas para que no se esquive cambiando mayúsculas. */
    private function accountKey(Request $request): string
    {
        return Str::lower((string) $request->input('email')) . '|' . $request->ip();
    }
}
