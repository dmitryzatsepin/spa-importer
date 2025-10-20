<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TestBitrix24Controller;

Route::get('/', function () {
    return view('welcome');
});

// Тестовые маршруты для Bitrix24 API Service
Route::prefix('test-bitrix24')->group(function () {
    Route::get('/', [TestBitrix24Controller::class, 'index']);
    Route::get('/single', [TestBitrix24Controller::class, 'testSingleCall']);
    Route::get('/batch', [TestBitrix24Controller::class, 'testBatchCall']);
    Route::get('/error', [TestBitrix24Controller::class, 'testErrorHandling']);
    Route::get('/invalid-token', [TestBitrix24Controller::class, 'testInvalidToken']);
});

