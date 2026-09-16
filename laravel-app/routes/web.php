<?php

use Illuminate\Support\Facades\Route;

/*
 * Este proyecto es solo API (la interfaz es la app de Expo), así que no hay
 * pantallas web. La raíz responde un chequeo de salud en JSON para que los
 * monitores de tiempo en línea y el healthcheck de la plataforma tengan a qué
 * pegarle sin recibir un 404.
 */
Route::get('/', function () {
    return response()->json([
        'service' => config('app.name'),
        'status' => 'ok',
    ]);
});
