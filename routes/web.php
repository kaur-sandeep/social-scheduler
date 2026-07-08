<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FacebookController;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('/calendar/events', [CalendarController::class, 'events'])->name('calendar.events');

    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/facebook/login', [FacebookController::class, 'redirect'])->name('facebook.redirect');
    Route::get('/facebook/callback', [FacebookController::class, 'callback'])->name('facebook.callback');
    Route::post('/facebook/accounts/{account}/disconnect', [FacebookController::class, 'disconnect'])->name('facebook.disconnect');

    Route::resource('posts', PostController::class)->only(['index', 'create', 'store', 'destroy']);
    Route::patch('/posts/{post}/move', [PostController::class, 'move'])->name('posts.move');

    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
    Route::view('/analytics', 'analytics.index')->name('analytics.index');
    Route::view('/media-library', 'media.index')->name('media.index');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
});
