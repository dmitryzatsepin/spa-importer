<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestBitrix24Controller;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('welcome');
});

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
});

