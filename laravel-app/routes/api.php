<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Rutas Públicas (No requieren Token)
Route::post('/login', [AuthController::class, 'login']);
// routes/api.php
Route::post('/seed', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();
        return response()->json([
            'message' => 'Seeders executed',
            'output' => $output
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'message' => 'Seeder failed',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

//Route::get('users', [App\Http\Controllers\UserController::class, 'index']);
// Rutas Protegidas (Requieren Token de Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth endpoints adicionales
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    
    // Rutas Reales CRUD completas (Protegidas)
    // Personal/Usuarios es 100% administrativo: todos los verbos requieren manage_users.
    Route::middleware('permission:manage_users')->group(function () {
        Route::apiResource('users', App\Http\Controllers\UserController::class);
    });

    // Residentes: ver está abierto a cualquier rol autenticado; crear/editar,
    // desactivar/eliminar/restaurar solo Admin (la administradora).
    Route::apiResource('residents', App\Http\Controllers\ResidentController::class)->only(['index', 'show']);
    Route::middleware('permission:create_residents|edit_residents')->group(function () {
        Route::apiResource('residents', App\Http\Controllers\ResidentController::class)->only(['store', 'update']);
    });
    Route::middleware('permission:delete_residents')->group(function () {
        Route::apiResource('residents', App\Http\Controllers\ResidentController::class)->only(['destroy']);
        Route::post('residents/{id}/restore', [App\Http\Controllers\ResidentController::class, 'restore']);
    });

    Route::apiResource('jobs', App\Http\Controllers\JobController::class);

    // Auditoría: solo lectura y solo Admin. Las filas las genera AuditableObserver,
    // nunca un cliente HTTP — por eso no hay store/update/destroy.
    Route::middleware('permission:manage_users')->group(function () {
        Route::apiResource('audit-logs', App\Http\Controllers\AuditLogController::class)->only(['index', 'show']);
    });

    // Catálogos de condiciones y medicamentos, y prescripciones: ver está abierto,
    // gestionarlos (crear/editar/eliminar) requiere manage_medications (Admin/Enfermera).
    Route::apiResource('diseases', App\Http\Controllers\DiseaseController::class)->only(['index', 'show']);
    Route::apiResource('medications', App\Http\Controllers\MedicationController::class)->only(['index', 'show']);
    Route::apiResource('prescriptions', App\Http\Controllers\PrescriptionController::class)->only(['index', 'show']);
    Route::middleware('permission:manage_medications')->group(function () {
        Route::apiResource('diseases', App\Http\Controllers\DiseaseController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('medications', App\Http\Controllers\MedicationController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('prescriptions', App\Http\Controllers\PrescriptionController::class)->only(['store', 'update', 'destroy']);
    });

    Route::apiResource('resident-images', App\Http\Controllers\ResidentImageController::class);
    Route::apiResource('resident-documents', App\Http\Controllers\ResidentDocumentController::class);
    Route::apiResource('resident-vitals', App\Http\Controllers\ResidentVitalController::class);
    Route::apiResource('disease-resident-assignments', App\Http\Controllers\DiseaseResidentAssignmentController::class);
    Route::apiResource('medication-alerts', App\Http\Controllers\MedicationAlertController::class);
    Route::apiResource('medication-schedules', App\Http\Controllers\MedicationScheduleController::class);

    // Marcar un medicamento como administrado/no administrado es la tarea clínica
    // central de Enfermera/Admin; Staff no tiene este permiso.
    Route::apiResource('medication-logs', App\Http\Controllers\MedicationLogController::class)->only(['index', 'show', 'update', 'destroy']);
    Route::middleware('permission:administer_medications')->group(function () {
        Route::apiResource('medication-logs', App\Http\Controllers\MedicationLogController::class)->only(['store']);
    });

    // Kardex de medicamentos: cualquier rol autenticado puede consultar el stock y su
    // historial de movimientos; solo Admin puede registrar entradas/salidas/ajustes.
    Route::apiResource('medication-stock-movements', App\Http\Controllers\MedicationStockMovementController::class)->only(['index', 'show']);
    Route::middleware('permission:manage_inventory')->group(function () {
        Route::apiResource('medication-stock-movements', App\Http\Controllers\MedicationStockMovementController::class)->only(['store']);
    });

    // Reportes en PDF: por residente (medicación/omisiones) y por enfermera (actividad).
    // Admin y Enfermera tienen view_reports; Staff no.
    Route::middleware('permission:view_reports')->prefix('reports')->group(function () {
        Route::get('residents/{id}/medications', [App\Http\Controllers\ReportController::class, 'residentMedicationPdf']);
        Route::get('nurses/{id}/activity', [App\Http\Controllers\ReportController::class, 'nursePdf']);
    });

    // Push notifications (Firebase Cloud Messaging)
    Route::post('/device-tokens', [App\Http\Controllers\DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [App\Http\Controllers\DeviceTokenController::class, 'destroy']);
    Route::post('/device-tokens/test', [App\Http\Controllers\DeviceTokenController::class, 'test']);
});
