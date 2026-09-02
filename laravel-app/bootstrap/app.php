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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Con redirectGuestsTo(null) de arriba, Authenticate ya no explota,
        // pero el Handler por defecto de Laravel todavía intenta
        // "$exception->redirectTo($request) ?? route('login')" cuando la
        // petición no pide JSON explícitamente. Este render() se adelanta a
        // eso y siempre responde JSON 401, sin importar el header Accept.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        });
    })->create();
