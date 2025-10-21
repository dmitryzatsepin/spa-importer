<?php

use App\Http\Controllers\Api\V1\ImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/smart-processes', [ImportController::class, 'getSmartProcesses']);
    Route::get('/smart-processes/{entityTypeId}/fields', [ImportController::class, 'getSmartProcessFields']);
    Route::post('/import', [ImportController::class, 'startImport']);
    Route::get('/import/{jobId}/status', [ImportController::class, 'getImportStatus']);
});

