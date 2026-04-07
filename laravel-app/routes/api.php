<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\AuthController;

// Rutas Públicas (No requieren Token)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Seeder endpoint — triggers db:seed via HTTP (use with caution in production)
Route::post('/seed', function () {
    try {
        Artisan::call('db:seed', ['--force' => true]);
        $output = Artisan::output();
        return response()->json([
            'success' => true,
            'message' => 'Database seeded successfully.',
            'output'  => $output,
        ], 200);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Seeding failed.',
            'error'   => $e->getMessage(),
        ], 500);
    }
});

// Rutas Protegidas (Requieren Token de Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth endpoints adicionales
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Rutas Reales CRUD completas (Protegidas)
    Route::apiResource('residents', App\Http\Controllers\ResidentController::class);
    Route::apiResource('users', App\Http\Controllers\UserController::class);
    Route::apiResource('jobs', App\Http\Controllers\JobController::class);
    Route::apiResource('audit-logs', App\Http\Controllers\AuditLogController::class);
    Route::apiResource('diseases', App\Http\Controllers\DiseaseController::class);
    Route::apiResource('notifications', App\Http\Controllers\NotificationController::class);
    Route::apiResource('resident-images', App\Http\Controllers\ResidentImageController::class);
    Route::apiResource('resident-reports', App\Http\Controllers\ResidentReportController::class);
    Route::apiResource('resident-vitals', App\Http\Controllers\ResidentVitalController::class);
    Route::apiResource('disease-resident-assignments', App\Http\Controllers\DiseaseResidentAssignmentController::class);
    Route::apiResource('medications', App\Http\Controllers\MedicationController::class);
    Route::apiResource('prescriptions', App\Http\Controllers\PrescriptionController::class);
    Route::apiResource('medication-alerts', App\Http\Controllers\MedicationAlertController::class);
    Route::apiResource('medication-schedules', App\Http\Controllers\MedicationScheduleController::class);
    Route::apiResource('medication-logs', App\Http\Controllers\MedicationLogController::class);
});
