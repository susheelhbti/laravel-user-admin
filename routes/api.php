<?php

use Illuminate\Support\Facades\Route;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth\OtpController;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Admin\UserManagementController;

$prefix     = config('user_admin.route_prefix', 'api');
$middleware = config('user_admin.route_middleware', []);

Route::prefix($prefix)->middleware($middleware)->group(function () {

    // ── Public auth ───────────────────────────────────────────────────────────
    Route::post('auth/otp/send',   [OtpController::class, 'sendOtp']);
    Route::post('auth/otp/verify', [OtpController::class, 'verifyOtp']);

    // ── Authenticated ──────────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [OtpController::class, 'logout']);
        Route::get('auth/me',      [OtpController::class, 'me']);

        // ── Admin ──────────────────────────────────────────────────────────────
        Route::middleware('user-admin.admin')->prefix('admin')->group(function () {

            // Stats & Logs
            Route::get('statistics',  [UserManagementController::class, 'statistics']);
            Route::get('admin-logs',  [UserManagementController::class, 'adminLogs']);

            // Users - list, create, export
            Route::get('users',        [UserManagementController::class, 'index']);
            Route::post('users',       [UserManagementController::class, 'store']);
            Route::get('users/export', [UserManagementController::class, 'export']);

            // Bulk operations (must come before {user} routes)
            Route::post('users/bulk/suspend',     [UserManagementController::class, 'bulkSuspend']);
            Route::post('users/bulk/unsuspend',   [UserManagementController::class, 'bulkUnsuspend']);
            Route::post('users/bulk/assign-role', [UserManagementController::class, 'bulkAssignRole']);
            Route::post('users/bulk/delete',      [UserManagementController::class, 'bulkDelete']);

            // Impersonation stop
            Route::post('users/stop-impersonation', [UserManagementController::class, 'stopImpersonation']);

            // Single user CRUD
            Route::get('users/{user}',        [UserManagementController::class, 'show']);
            Route::put('users/{user}',        [UserManagementController::class, 'update']);
            Route::delete('users/{user}',     [UserManagementController::class, 'destroy']);
            Route::delete('users/{user}/soft',[UserManagementController::class, 'softDelete']);

            // Single user actions
            Route::post('users/{user}/suspend',              [UserManagementController::class, 'suspend']);
            Route::post('users/{user}/unsuspend',            [UserManagementController::class, 'unsuspend']);
            Route::post('users/{user}/temporary-ban',        [UserManagementController::class, 'temporaryBan']);
            Route::post('users/{user}/force-password-reset', [UserManagementController::class, 'forcePasswordReset']);
            Route::post('users/{user}/remove-2fa',           [UserManagementController::class, 'removeTwoFactor']);
            Route::post('users/{user}/terminate-sessions',   [UserManagementController::class, 'terminateAllSessions']);
            Route::get('users/{user}/login-history',         [UserManagementController::class, 'loginHistory']);
            Route::post('users/{user}/impersonate',          [UserManagementController::class, 'impersonate']);
        });
    });
});
