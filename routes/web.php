<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/generate', [HomeController::class, 'generate'])
    ->middleware('throttle:visitor-action')
    ->name('generate');
Route::get('/api/dashboard', [HomeController::class, 'dashboard'])
    ->middleware('throttle:visitor-dashboard')
    ->name('dashboard');
Route::post('/api/visitor-status', [HomeController::class, 'visitorStatus'])
    ->middleware('throttle:visitor-action')
    ->name('visitor-status');
Route::post('/api/visitor-pass', [HomeController::class, 'visitorPass'])
    ->middleware('throttle:visitor-email')
    ->name('visitor-pass');
