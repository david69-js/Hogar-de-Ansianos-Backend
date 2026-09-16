<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Rutas Públicas (No requieren Token)
// throttle: los límites están definidos en AppServiceProvider::registerRateLimiters().
// Se cuentan por cuenta + IP para que el error de una persona no bloquee al resto
// del hogar, que sale por una sola conexión.
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Recuperación de contraseña: públicas por necesidad — son para quien no puede
// entrar y por tanto no tiene token. La protección va dentro del controlador
// (respuesta genérica, código hasheado, vencimiento, límite de intentos).
Route::post('/password/forgot', [App\Http\Controllers\PasswordResetController::class, 'forgot'])
    ->middleware('throttle:password-forgot');
Route::post('/password/reset', [App\Http\Controllers\PasswordResetController::class, 'reset'])
    ->middleware('throttle:password-reset');

// Rutas Protegidas (Requieren Token de Sanctum)
// No hay endpoint HTTP para sembrar la base: uno público permitiría a
// cualquiera recrear admin@hogar.com con la contraseña por defecto del
// UserSeeder (que está en el repo). En producción se siembra por consola:
//   railway ssh ... "php artisan migrate:fresh --seed --force"
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth endpoints adicionales
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    
    // Rutas Reales CRUD completas (Protegidas)
    // Personal/Usuarios es 100% administrativo: todos los verbos requieren manage_users.
    Route::middleware('permission:manage_users')->group(function () {
        Route::apiResource('users', App\Http\Controllers\UserController::class);
        // Asignar la enfermera responsable de un residente: decisión de la
        // administradora (manage_users), no de quien edita la ficha.
        Route::put('residents/{id}/assigned-nurse', [App\Http\Controllers\ResidentController::class, 'assignNurse']);
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

    // Fotos/documentos de residentes, signos vitales y asignación de condiciones:
    // ver está abierto a cualquier rol autenticado (igual que residents/diseases);
    // antes estos 4 recursos solo exigían auth:sanctum en todos los verbos, así
    // que cualquier usuario autenticado (incluido Staff) podía crear/editar/borrar
    // por API directa aunque el frontend ocultara esos botones. Encontrado en la
    // ronda de QA de 2026-09.
    Route::apiResource('resident-images', App\Http\Controllers\ResidentImageController::class)->only(['index', 'show']);
    Route::apiResource('resident-documents', App\Http\Controllers\ResidentDocumentController::class)->only(['index', 'show']);
    Route::apiResource('resident-vitals', App\Http\Controllers\ResidentVitalController::class)->only(['index', 'show']);
    Route::apiResource('disease-resident-assignments', App\Http\Controllers\DiseaseResidentAssignmentController::class)->only(['index', 'show']);
    Route::middleware('permission:edit_residents')->group(function () {
        Route::apiResource('resident-images', App\Http\Controllers\ResidentImageController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('resident-documents', App\Http\Controllers\ResidentDocumentController::class)->only(['store', 'update', 'destroy']);
    });
    Route::middleware('permission:manage_medications')->group(function () {
        Route::apiResource('resident-vitals', App\Http\Controllers\ResidentVitalController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('disease-resident-assignments', App\Http\Controllers\DiseaseResidentAssignmentController::class)->only(['store', 'update', 'destroy']);
    });

    // Sin store/destroy: esas filas solo las crean/borran los comandos programados
    // por Eloquent directo (ver MedicationAlertController). update() solo permite
    // marcar `read_at` — cualquier usuario autenticado puede leer/marcar leída su
    // bandeja de notificaciones, es una bandeja compartida por todo el equipo.
    Route::apiResource('medication-alerts', App\Http\Controllers\MedicationAlertController::class)->only(['index', 'show', 'update']);
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

    // Reportes de consulta clínica: la enfermera los necesita para su propio trabajo
    // (qué toma el residente, qué se omitió, quiénes están internados).
    // Admin y Enfermera tienen view_reports; Staff no.
    Route::middleware('permission:view_reports')->prefix('reports')->group(function () {
        Route::get('residents/{id}/medications', [App\Http\Controllers\ReportController::class, 'residentMedicationPdf']);
        Route::get('incidents', [App\Http\Controllers\ReportController::class, 'incidentsPdf']);
        Route::get('residents', [App\Http\Controllers\ReportController::class, 'residentsPdf']);
    });

    // Reportes de supervisión: miden el desempeño del personal (actividad y omisiones
    // por enfermera, cumplimiento del tratamiento). Son para quien supervisa, no para
    // la enfermera supervisada, así que van con su propio permiso, solo de Admin.
    Route::middleware('permission:view_management_reports')->prefix('reports')->group(function () {
        Route::get('compliance', [App\Http\Controllers\ReportController::class, 'compliancePdf']);
        Route::get('nurses/{id}/activity', [App\Http\Controllers\ReportController::class, 'nursePdf']);
    });

    // Push notifications (Firebase Cloud Messaging)
    Route::post('/device-tokens', [App\Http\Controllers\DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [App\Http\Controllers\DeviceTokenController::class, 'destroy']);
    Route::post('/device-tokens/test', [App\Http\Controllers\DeviceTokenController::class, 'test']);
});
