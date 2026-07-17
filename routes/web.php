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
use App\Http\Controllers\YouTubeController;
use App\Http\Controllers\LinkedInController;
use App\Http\Controllers\TwitterController;
use App\Http\Controllers\PinterestController;
use App\Http\Controllers\TikTokController;
use App\Http\Controllers\Admin\ProjectSettingsController;

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
    Route::get('/youtube/login', [YouTubeController::class, 'redirect'])->name('youtube.redirect');
    Route::get('/youtube/callback', [YouTubeController::class, 'callback'])->name('youtube.callback');
    Route::post('/youtube/accounts/{account}/disconnect', [YouTubeController::class, 'disconnect'])->name('youtube.disconnect');
    Route::get('/linkedin/login', [LinkedInController::class, 'redirect'])->name('linkedin.redirect');
    Route::get('/linkedin/callback', [LinkedInController::class, 'callback'])->name('linkedin.callback');
    Route::post('/linkedin/accounts/{account}/disconnect', [LinkedInController::class, 'disconnect'])->name('linkedin.disconnect');
    Route::get('/twitter/login', [TwitterController::class, 'redirect'])->name('twitter.redirect');
    Route::get('/twitter/callback', [TwitterController::class, 'callback'])->name('twitter.callback');
    Route::post('/twitter/accounts/{account}/disconnect', [TwitterController::class, 'disconnect'])->name('twitter.disconnect');
    Route::get('/pinterest/login', [PinterestController::class, 'redirect'])->name('pinterest.redirect');
    Route::get('/pinterest/callback', [PinterestController::class, 'callback'])->name('pinterest.callback');
    Route::post('/pinterest/accounts/{account}/disconnect', [PinterestController::class, 'disconnect'])->name('pinterest.disconnect');
    Route::get('/tiktok/login', [TikTokController::class, 'redirect'])->name('tiktok.redirect');
    Route::get('/tiktok/callback', [TikTokController::class, 'callback'])->name('tiktok.callback');
    Route::post('/tiktok/accounts/{account}/disconnect', [TikTokController::class, 'disconnect'])->name('tiktok.disconnect');

    Route::resource('posts', PostController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::get('/posts/deleted', [PostController::class, 'deleted'])->name('posts.deleted');
    Route::patch('/posts/{post}/restore', [PostController::class, 'restore'])->name('posts.restore');
    Route::delete('/posts/{post}/permanent', [PostController::class, 'forceDestroy'])->name('posts.force-destroy');
    Route::get('/posts/pages', [PostController::class, 'pages'])->name('posts.pages');
    Route::patch('/posts/{post}/move', [PostController::class, 'move'])->name('posts.move');

    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
    Route::view('/analytics', 'analytics.index')->name('analytics.index');
    Route::view('/media-library', 'media.index')->name('media.index');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/project-settings', [ProjectSettingsController::class, 'index'])->name('project-settings.index');
    Route::post('/project-settings/projects', [ProjectSettingsController::class, 'store'])->name('project-settings.store');
    Route::put('/project-settings/{project}', [ProjectSettingsController::class, 'update'])->name('project-settings.update');
    Route::delete('/project-settings/{project}', [ProjectSettingsController::class, 'destroy'])->name('project-settings.destroy');
    Route::patch('/project-settings/{project}/restore', [ProjectSettingsController::class, 'restore'])->name('project-settings.restore');
    Route::delete('/project-settings/{project}/permanent', [ProjectSettingsController::class, 'forceDestroy'])->name('project-settings.force-destroy');
});
