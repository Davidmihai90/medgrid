<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\OrganizationController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SystemHealthController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AmbulanceClinicalController;
use App\Http\Controllers\AmbulanceMissionController;
use App\Http\Controllers\AmbulanceMissionStateController;
use App\Http\Controllers\ApplicationShellController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DispatchAssignmentController;
use App\Http\Controllers\DispatchAssignmentManagementController;
use App\Http\Controllers\DispatchBoardController;
use App\Http\Controllers\DispatchCaseController;
use App\Http\Controllers\OperationalNoticeController;
use App\Http\Controllers\OrganizationContextController;
use Illuminate\Support\Facades\Route;

Route::get('/health/live', fn () => response()->json(['status' => 'ok']))->name('health.live');
Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:login')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:login')->name('password.store');
});
Route::middleware(['auth', 'active', 'current.organization'])->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::put('/organization-context/{organization}', [OrganizationContextController::class, 'update'])->name('organization-context.update');
    Route::middleware('area:dispatch')->group(function () {
        Route::get('/dispatch', DispatchBoardController::class)->name('area.dispatch');
        Route::post('/dispatch/cases', [DispatchCaseController::class, 'store'])->name('dispatch.cases.store');
        Route::post('/dispatch/cases/{emergencyCase}/triage', [DispatchCaseController::class, 'triage'])->name('dispatch.cases.triage');
        Route::post('/dispatch/cases/{emergencyCase}/assignments', [DispatchAssignmentController::class, 'store'])->name('dispatch.assignments.store');
        Route::put('/dispatch/assignments/{assignment}', [DispatchAssignmentManagementController::class, 'update'])->name('dispatch.assignments.update');
        Route::delete('/dispatch/assignments/{assignment}', [DispatchAssignmentManagementController::class, 'destroy'])->name('dispatch.assignments.destroy');
    });
    Route::middleware('area:ambulance')->group(function () {
        Route::get('/ambulance', AmbulanceMissionController::class)->name('area.ambulance');
        Route::post('/ambulance/assignments/{assignment}/delivery', [AmbulanceMissionStateController::class, 'deliver'])->name('ambulance.delivery');
        Route::post('/ambulance/assignments/{assignment}/acceptance', [AmbulanceMissionStateController::class, 'accept'])->name('ambulance.acceptance');
        Route::post('/ambulance/cases/{case}/workflow', [AmbulanceClinicalController::class, 'workflow'])->name('ambulance.workflow');
        Route::post('/ambulance/cases/{case}/encounters', [AmbulanceClinicalController::class, 'encounter'])->name('ambulance.encounters.store');
        Route::put('/ambulance/encounters/{encounter}/patients/{patient}', [AmbulanceClinicalController::class, 'patient'])->name('ambulance.patients.update');
        Route::post('/ambulance/encounters/{encounter}/vitals', [AmbulanceClinicalController::class, 'vital'])->name('ambulance.vitals.store');
        Route::put('/ambulance/encounters/{encounter}/condition', [AmbulanceClinicalController::class, 'condition'])->name('ambulance.condition.update');
        Route::post('/ambulance/encounters/{encounter}/notes', [AmbulanceClinicalController::class, 'note'])->name('ambulance.notes.store');
        Route::post('/ambulance/encounters/{encounter}/assessments/{version}', [AmbulanceClinicalController::class, 'start'])->name('ambulance.assessments.start');
        Route::put('/ambulance/assessments/{assessment}/responses', [AmbulanceClinicalController::class, 'responses'])->name('ambulance.assessments.responses');
        Route::post('/ambulance/assessments/{assessment}/complete', [AmbulanceClinicalController::class, 'complete'])->name('ambulance.assessments.complete');
    });
    foreach (['hospital', 'medical', 'control'] as $area) {
        Route::get('/'.$area, ApplicationShellController::class)->defaults('area', $area)->middleware('area:'.$area)->name('area.'.$area);
    }Route::prefix('admin')->name('admin.')->middleware('area:admin')->group(function () {
        Route::get('/', fn () => view('admin.index'))->name('index');
        Route::resource('organizations', OrganizationController::class)->except('destroy');
        Route::resource('users', UserController::class)->except('destroy');
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/system/health', SystemHealthController::class)->name('health');
        Route::post('/operational-notice', [OperationalNoticeController::class, 'store'])->name('operational-notice.store');
    });
});
