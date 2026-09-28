<?php

use App\Http\Controllers\Api\AssignmentManagementController;
use App\Http\Controllers\Api\CaseAssignmentController;
use App\Http\Controllers\Api\CaseTriageController;
use App\Http\Controllers\Api\ClinicalController;
use App\Http\Controllers\Api\EmergencyCaseController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MissionAcceptanceController;
use App\Http\Controllers\Api\MissionDeliveryController;
use App\Http\Controllers\Api\SyncController;
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
    Route::post('/cases/{case}/workflow', [ClinicalController::class, 'workflow']);
    Route::get('/cases/{case}/encounters', [ClinicalController::class, 'encounters']);
    Route::post('/cases/{case}/encounters', [ClinicalController::class, 'createEncounter']);
    Route::get('/encounters/{encounter}', [ClinicalController::class, 'encounter']);
    Route::put('/encounters/{encounter}/patients/{patient}', [ClinicalController::class, 'updatePatient']);
    Route::get('/encounters/{encounter}/vitals', [ClinicalController::class, 'vitals']);
    Route::post('/encounters/{encounter}/vitals', [ClinicalController::class, 'createVital']);
    Route::post('/encounters/{encounter}/notes', [ClinicalController::class, 'note']);
    Route::post('/vitals/{vital}/correct', [ClinicalController::class, 'correctVital']);
    Route::put('/encounters/{encounter}/condition', [ClinicalController::class, 'condition']);
    Route::post('/encounters/{encounter}/assessments/{version}', [ClinicalController::class, 'startAssessment']);
    Route::put('/assessments/{assessment}/responses', [ClinicalController::class, 'responses']);
    Route::post('/assessments/{assessment}/complete', [ClinicalController::class, 'complete']);
    Route::post('/sync/operations', SyncController::class);
});
