<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EjemploController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// -------------------------------------------------------------
// Rutas de Ejemplo para el CRUD (Create, Read, Update, Delete)
// -------------------------------------------------------------

// Leer todos los registros activos
Route::get('/ejemplos', [EjemploController::class, 'index']);

// Leer un registro en especifico
Route::get('/ejemplos/{id}', [EjemploController::class, 'show']);

// Crear un nuevo registro
Route::post('/ejemplos', [EjemploController::class, 'store']);

// Actualizar un registro
Route::put('/ejemplos/{id}', [EjemploController::class, 'update']);

// "Eliminar" un registro (solo cambia el estado a inactivo)
Route::delete('/ejemplos/{id}', [EjemploController::class, 'destroy']);
