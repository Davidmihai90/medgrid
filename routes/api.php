<?php

use App\Http\Controllers\Api\AssignmentManagementController;
use App\Http\Controllers\Api\CaseAssignmentController;
use App\Http\Controllers\Api\CaseTriageController;
use App\Http\Controllers\Api\EmergencyCaseController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MissionAcceptanceController;
use App\Http\Controllers\Api\MissionDeliveryController;
use App\Http\Controllers\Api\SystemHealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['web', 'auth', 'active', 'current.organization', 'throttle:api'])->group(function () {
    Route::get('/me', MeController::class);
    Route::get('/system/health', SystemHealthController::class);
    Route::apiResource('cases', EmergencyCaseController::class)->only(['index', 'store', 'show'])->parameters(['cases' => 'emergencyCase']);
    Route::post('/cases/{emergencyCase}/triage', [CaseTriageController::class, 'store']);
    Route::post('/cases/{emergencyCase}/assignments', [CaseAssignmentController::class, 'store']);
    Route::put('/assignments/{assignment}', [AssignmentManagementController::class, 'update']);
    Route::delete('/assignments/{assignment}', [AssignmentManagementController::class, 'destroy']);
    Route::post('/assignments/{assignment}/delivery', [MissionDeliveryController::class, 'store']);
    Route::post('/assignments/{assignment}/acceptance', [MissionAcceptanceController::class, 'store']);
});
