<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\JournalController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user()->load('branch');
    });

    Route::post('/journals', [JournalController::class, 'store']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/checkout', [CheckoutController::class, 'store']);
});
