<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Rutas Reales CRUD completas
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
