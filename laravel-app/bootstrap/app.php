<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);

        // Por defecto, ApplicationBuilder configura redirectGuestsTo(fn () =>
        // route('login')). Esta app es una API pura (tokens Sanctum, sin
        // sesiones de navegador) y no tiene ninguna ruta 'login' registrada:
        // sin este override, una petición no autenticada que no mande
        // "Accept: application/json" (curl por defecto, algunos monitores de
        // uptime) hace que el middleware Authenticate intente resolver
        // route('login') y explote con RouteNotFoundException — 500 genérico
        // en vez del 401 real.
        $middleware->redirectGuestsTo(fn () => null);

        // La app nunca recibe tráfico directo: adelante siempre hay un proxy
        // (el de Railway hoy, nginx si se pasa a servidor propio). Sin esto
        // Laravel cree que la petición llegó por http y desde la IP del proxy,
        // así que los enlaces absolutos salen en http y los límites de intentos
        // contarían a TODOS los usuarios como una sola IP — la del proxy.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Con redirectGuestsTo(null) de arriba, Authenticate ya no explota,
        // pero el Handler por defecto de Laravel todavía intenta
        // "$exception->redirectTo($request) ?? route('login')" cuando la
        // petición no pide JSON explícitamente. Este render() se adelanta a
        // eso y siempre responde JSON 401, sin importar el header Accept.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            return response()->json(['message' => 'Tu sesión expiró o no has iniciado sesión.'], 401);
        });

        // Las pantallas muestran el "message" de la API tal cual en un aviso; estos
        // errores del framework y de Spatie venían en inglés. Solo se reemplaza el
        // texto — el código HTTP sigue siendo el mismo.
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            return response()->json(['message' => 'No tienes permiso para realizar esta acción.'], 403);
        });
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, $request) {
            return response()->json(
                ['message' => 'Demasiados intentos. Espera un momento e inténtalo de nuevo.'],
                429,
                $e->getHeaders()
            );
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            // findOrFail() sin registro llega aquí con "No query results for model
            // [App\Models\...]", que además exponía el nombre interno del modelo.
            // Un abort(404, 'mensaje') propio (ya en español) se respeta.
            $message = $e->getPrevious() instanceof \Illuminate\Database\Eloquent\ModelNotFoundException || $e->getMessage() === ''
                ? 'El registro solicitado no existe o fue eliminado.'
                : $e->getMessage();
            return response()->json(['message' => $message], 404);
        });
    })->create();
