<?php

use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\MockImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Мок API для демонстрации фронтенда
    Route::get('/smart-processes', [MockImportController::class, 'getSmartProcesses']);
    Route::get('/smart-processes/{entityTypeId}/fields', [MockImportController::class, 'getSmartProcessFields']);
    Route::post('/import', [MockImportController::class, 'startImport']);
    Route::get('/import/{jobId}/status', [MockImportController::class, 'getImportStatus']);

    // Реальный API (закомментирован для демонстрации)
    // Route::get('/smart-processes', [ImportController::class, 'getSmartProcesses']);
    // Route::get('/smart-processes/{entityTypeId}/fields', [ImportController::class, 'getSmartProcessFields']);
    // Route::post('/import', [ImportController::class, 'startImport']);
    // Route::get('/import/{jobId}/status', [ImportController::class, 'getImportStatus']);
});

