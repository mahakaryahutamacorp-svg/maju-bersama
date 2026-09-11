<?php

use App\Http\Controllers\PosController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Web\InventoryController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PreviewController::class, 'index']);
Route::get('/inventory', [InventoryController::class, 'index']);
Route::get('/pos', [PosController::class, 'index']);
Route::get('/reports/journal', [ReportController::class, 'journal']);
