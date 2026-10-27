<?php

use App\Http\Controllers\ApiRequestLogController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\ApplicationCredentialController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\NotificationFeedController;
use App\Http\Controllers\NotificationSettingController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');
Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::middleware(['auth', EnsureActiveUser::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account/password', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::put('/account/password', [AuthController::class, 'updatePassword'])->middleware('throttle:5,1')->name('password.update');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/notifications/feed', NotificationFeedController::class)->name('notifications.feed');
    Route::get('/dashboard/live-summary', [DashboardController::class, 'liveSummary'])->name('dashboard.live-summary');
    Route::get('/applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::get('/applications/create', [ApplicationController::class, 'create'])->middleware('can:admin')->name('applications.create');
    Route::get('/applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::get('/issues', [IssueController::class, 'index'])->name('issues.index');
    Route::get('/issues/{issue}', [IssueController::class, 'show'])->name('issues.show');

    Route::middleware('can:admin')->group(function () {
        Route::post('/applications', [ApplicationController::class, 'store'])->name('applications.store');
        Route::get('/applications/{application}/edit', [ApplicationController::class, 'edit'])->name('applications.edit');
        Route::put('/applications/{application}', [ApplicationController::class, 'update'])->name('applications.update');
        Route::post('/applications/{application}/toggle', [ApplicationController::class, 'toggle'])->name('applications.toggle');
        Route::post('/applications/{application}/credentials', [ApplicationCredentialController::class, 'store'])->name('credentials.store');
        Route::post('/applications/{application}/credentials/rotate', [ApplicationCredentialController::class, 'rotate'])->name('credentials.rotate');
        Route::post('/applications/{application}/credentials/{credential}/revoke', [ApplicationCredentialController::class, 'revoke'])->name('credentials.revoke');
        Route::post('/issues/{issue}/status', [IssueController::class, 'status'])->name('issues.status');
        Route::post('/issues/{issue}/severity', [IssueController::class, 'severity'])->name('issues.severity');
        Route::post('/issues/{issue}/comments', [IssueController::class, 'comment'])->name('issues.comments');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('/api-logs', [ApiRequestLogController::class, 'index'])->name('api-logs.index');
        Route::get('/settings/notifications', [NotificationSettingController::class, 'index'])->name('notifications.index');
        Route::get('/settings/notifications/create', [NotificationSettingController::class, 'create'])->name('notifications.create');
        Route::post('/settings/notifications', [NotificationSettingController::class, 'store'])->name('notifications.store');
        Route::get('/settings/notifications/{rule}/edit', [NotificationSettingController::class, 'edit'])->name('notifications.edit');
        Route::put('/settings/notifications/{rule}', [NotificationSettingController::class, 'update'])->name('notifications.update');
    });
});
