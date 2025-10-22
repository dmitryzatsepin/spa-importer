<?php

use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\MockImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Динамический выбор контроллера на основе config('app.api_use_mock')
    $controller = config('app.api_use_mock') ? MockImportController::class : ImportController::class;

    Route::get('/smart-processes', [$controller, 'getSmartProcesses']);
    Route::get('/smart-processes/{entityTypeId}/fields', [$controller, 'getSmartProcessFields']);
    Route::post('/import', [$controller, 'startImport']);
    Route::get('/import/{jobId}/status', [$controller, 'getImportStatus']);
    Route::get('/import/history', [$controller, 'history']);
    Route::get('/import/{jobId}/error-log', [$controller, 'downloadErrorLog']);
});

