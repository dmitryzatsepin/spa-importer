<?php

use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\MockImportController;
use Illuminate\Support\Facades\Route;

// В проде требуем API-ключ, локально можно тестировать без него
$middlewares = app()->environment('production') ? ['api.key'] : [];
Route::prefix('v1')->middleware($middlewares)->group(function () {
    // Динамический выбор контроллера на основе config('app.api_use_mock')
    Route::get('/smart-processes', function () {
        $controller = config('app.api_use_mock') ? MockImportController::class : ImportController::class;
        return app($controller)->getSmartProcesses(request());
    });

    Route::get('/smart-processes/{entityTypeId}/fields', function ($entityTypeId) {
        $controller = config('app.api_use_mock') ? MockImportController::class : ImportController::class;
        return app($controller)->getSmartProcessFields(request(), $entityTypeId);
    });

    Route::post('/import', function () {
        $controller = config('app.api_use_mock') ? MockImportController::class : ImportController::class;
        return app($controller)->startImport(request());
    });

    Route::get('/import/{jobId}/status', function ($jobId) {
        $controller = config('app.api_use_mock') ? MockImportController::class : ImportController::class;
        return app($controller)->getImportStatus($jobId);
    });

    Route::get('/import/history', function () {
        $controller = config('app.api_use_mock') ? MockImportController::class : ImportController::class;
        return app($controller)->history(request());
    });

    Route::get('/import/{jobId}/error-log', function ($jobId) {
        $controller = config('app.api_use_mock') ? MockImportController::class : ImportController::class;
        return app($controller)->downloadErrorLog($jobId);
    });
});

