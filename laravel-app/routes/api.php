<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Rutas Públicas (No requieren Token)
Route::post('/login', [AuthController::class, 'login']);

// routes/api.php
Route::post('/seed', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('db:seed');
        $output = \Illuminate\Support\Facades\Artisan::output();
        return response()->json(['message' => 'Seeders executed', 'output' => $output]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::get('/debug', function () {
    try {
        $tables = \Illuminate\Support\Facades\DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        $userCount = \App\Models\User::count();
        $users = \App\Models\User::select('id', 'email', 'status', 'role')->get();

        $rolesTableExists = \Illuminate\Support\Facades\Schema::hasTable('roles');
        $roles = $rolesTableExists
            ? \Spatie\Permission\Models\Role::all(['id', 'name'])
            : 'tabla roles no existe';

        return response()->json([
            'db_tables' => $tables,
            'user_count' => $userCount,
            'users' => $users,
            'roles_table_exists' => $rolesTableExists,
            'roles' => $roles,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Rutas Protegidas (Requieren Token de Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    
    // Auth endpoints adicionales
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    
    // Rutas Reales CRUD completas (Protegidas)
    Route::apiResource('users', App\Http\Controllers\UserController::class);
    Route::apiResource('residents', App\Http\Controllers\ResidentController::class);
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
