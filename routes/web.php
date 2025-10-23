<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestBitrix24Controller;
use App\Http\Controllers\AuthController;

// OAuth маршруты для установки приложения Битрикс24
Route::get('/install', [AuthController::class, 'install'])->name('auth.install');
Route::get('/auth/callback', [AuthController::class, 'callback'])->name('auth.callback');

// Тестовые маршруты для Bitrix24 API Service (только в dev/staging или при ENABLE_TEST_ROUTES=true)
if (config('app.env') !== 'production' || env('ENABLE_TEST_ROUTES', false)) {
    Route::prefix('test-bitrix24')->group(function () {
        Route::get('/', [TestBitrix24Controller::class, 'index']);
        Route::get('/single', [TestBitrix24Controller::class, 'testSingleCall']);
        Route::get('/batch', [TestBitrix24Controller::class, 'testBatchCall']);
        Route::get('/error', [TestBitrix24Controller::class, 'testErrorHandling']);
        Route::get('/invalid-token', [TestBitrix24Controller::class, 'testInvalidToken']);
        Route::get('/token-refresh', [TestBitrix24Controller::class, 'testTokenRefresh']);
    });
}

// Главный маршрут SPA - должен быть последним, чтобы не перекрывать другие маршруты
Route::get('/{any?}', function () {
    // Берём контекст из сессии, а не из query
    return view('app', [
        'member_id' => session('member_id'),
        'domain' => session('domain'),
        'portal_id' => session('portal_id'),
    ]);
})->where('any', '^(?!api).*$');

