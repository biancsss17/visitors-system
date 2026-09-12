<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/generate', [HomeController::class, 'generate'])->name('generate');
Route::get('/api/dashboard', [HomeController::class, 'dashboard'])->name('dashboard');
Route::post('/api/visitor-status', [HomeController::class, 'visitorStatus'])->name('visitor-status');
Route::post('/api/visitor-pass', [HomeController::class, 'visitorPass'])->name('visitor-pass');
