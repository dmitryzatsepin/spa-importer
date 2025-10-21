<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestBitrix24Controller;
use App\Http\Controllers\AuthController;

// OAuth маршруты для установки приложения Битрикс24
Route::get('/install', [AuthController::class, 'install'])->name('auth.install');
Route::get('/auth/callback', [AuthController::class, 'callback'])->name('auth.callback');

// Тестовые маршруты для Bitrix24 API Service
Route::prefix('test-bitrix24')->group(function () {
    Route::get('/', [TestBitrix24Controller::class, 'index']);
    Route::get('/single', [TestBitrix24Controller::class, 'testSingleCall']);
    Route::get('/batch', [TestBitrix24Controller::class, 'testBatchCall']);
    Route::get('/error', [TestBitrix24Controller::class, 'testErrorHandling']);
    Route::get('/invalid-token', [TestBitrix24Controller::class, 'testInvalidToken']);
    Route::get('/token-refresh', [TestBitrix24Controller::class, 'testTokenRefresh']);
});

// Главный маршрут SPA - должен быть последним, чтобы не перекрывать другие маршруты
Route::get('/{any?}', function () {
    // В реальном приложении здесь нужно получать данные из сессии или параметров установки
    // Для примера передаем тестовые данные
    return view('app', [
        'member_id' => request()->query('member_id'),
        'domain' => request()->query('domain'),
        'portal_id' => request()->query('portal_id', 1), // ID из таблицы portals
    ]);
})->where('any', '^(?!api).*$');

