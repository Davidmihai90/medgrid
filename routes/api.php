<?php

use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\SystemHealthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['web', 'auth', 'active', 'current.organization', 'throttle:api'])->group(function () {
    Route::get('/me', MeController::class);
    Route::get('/system/health', SystemHealthController::class);
});
