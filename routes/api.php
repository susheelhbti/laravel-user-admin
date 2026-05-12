<?php

use Illuminate\Support\Facades\Route;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\HealthController;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth\OtpController;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth\TwoFactorController;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth\TokenController;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth\DeviceController;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth\AccountController;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth\ProfileController;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth\ApiKeyController;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Admin\UserManagementController;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Admin\ImportController;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Admin\TeamController;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Admin\WebhookController;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Admin\AnalyticsController;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Admin\GdprController;

$prefix     = config('user_admin.route_prefix', 'api');
$middleware = config('user_admin.route_middleware', []);

Route::prefix($prefix)->middleware($middleware)->group(function () {

    // ── Health ─────────────────────────────────────────────────────────────────
    Route::get('health', HealthController::class);

    // ── Public auth ────────────────────────────────────────────────────────────
    Route::post('auth/otp/send',   [OtpController::class, 'sendOtp']);
    Route::post('auth/otp/verify', [OtpController::class, 'verifyOtp'])->middleware('throttle:10,5');

    // ── Authenticated ──────────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Core auth
        Route::post('auth/logout',            [OtpController::class, 'logout']);
        Route::get('auth/me',                 [OtpController::class, 'me']);
        Route::post('auth/token/refresh',     [TokenController::class, 'refresh']);

        // 2FA
        Route::prefix('auth/2fa')->group(function () {
            Route::get('setup',                    [TwoFactorController::class, 'setup']);
            Route::post('confirm',                 [TwoFactorController::class, 'confirm']);
            Route::post('disable',                 [TwoFactorController::class, 'disable']);
            Route::post('backup-codes/regenerate', [TwoFactorController::class, 'regenerateBackupCodes']);
        });

        // Trusted devices
        Route::prefix('auth/devices')->group(function () {
            Route::get('/',           [DeviceController::class, 'index']);
            Route::post('trust',      [DeviceController::class, 'trust']);
            Route::delete('/',        [DeviceController::class, 'revokeAll']);
            Route::delete('{device}', [DeviceController::class, 'revoke']);
        });

        // Account deletion
        Route::post('auth/account/request-deletion', [AccountController::class, 'requestDeletion']);
        Route::post('auth/account/cancel-deletion',  [AccountController::class, 'cancelDeletion']);

        // Profile
        Route::get('auth/profile/score',          [ProfileController::class, 'score']);
        Route::put('auth/profile/metadata',        [ProfileController::class, 'updateMetadata']);

        // GDPR (self-service)
        Route::get('auth/gdpr/export',             [ProfileController::class, 'gdprExport']);
        Route::post('auth/gdpr/consent',           [ProfileController::class, 'updateConsent']);

        // Security questions
        Route::get('auth/security-questions/bank',  [ProfileController::class, 'securityQuestionsBank']);
        Route::post('auth/security-questions/set',  [ProfileController::class, 'setSecurityQuestions']);

        // API keys (self-service)
        Route::get('auth/api-keys',                 [ApiKeyController::class, 'index']);
        Route::post('auth/api-keys',                [ApiKeyController::class, 'store']);
        Route::post('auth/api-keys/{apiKey}/rotate',[ApiKeyController::class, 'rotate']);
        Route::delete('auth/api-keys/{apiKey}',     [ApiKeyController::class, 'destroy']);

        // ── Admin ──────────────────────────────────────────────────────────────
        Route::middleware('user-admin.admin')->prefix('admin')->group(function () {

            // ── Statistics & Logs ──────────────────────────────────────────────
            Route::get('statistics',       [UserManagementController::class, 'statistics']);
            Route::get('admin-logs',       [UserManagementController::class, 'adminLogs']);

            // ── Analytics ──────────────────────────────────────────────────────
            Route::get('analytics',         [AnalyticsController::class, 'dashboard']);
            Route::get('analytics/metrics', [AnalyticsController::class, 'metrics']);   // Prometheus

            // ── Webhooks ───────────────────────────────────────────────────────
            Route::get('webhooks',                              [WebhookController::class, 'index']);
            Route::post('webhooks',                             [WebhookController::class, 'store']);
            Route::put('webhooks/{endpoint}',                   [WebhookController::class, 'update']);
            Route::delete('webhooks/{endpoint}',                [WebhookController::class, 'destroy']);
            Route::post('webhooks/{endpoint}/rotate-secret',    [WebhookController::class, 'rotateSecret']);
            Route::get('webhooks/{endpoint}/deliveries',        [WebhookController::class, 'deliveries']);
            Route::post('webhooks/{endpoint}/test',             [WebhookController::class, 'testDeliver']);
            Route::get('webhooks/events',                       [WebhookController::class, 'availableEvents']);

            // ── Teams ──────────────────────────────────────────────────────────
            Route::get('teams',                             [TeamController::class, 'index']);
            Route::post('teams',                            [TeamController::class, 'store']);
            Route::get('teams/{team}',                      [TeamController::class, 'show']);
            Route::put('teams/{team}',                      [TeamController::class, 'update']);
            Route::delete('teams/{team}',                   [TeamController::class, 'destroy']);
            Route::post('teams/{team}/members',             [TeamController::class, 'addMember']);
            Route::delete('teams/{team}/members',           [TeamController::class, 'removeMember']);
            Route::post('teams/{team}/transfer-ownership',  [TeamController::class, 'transferOwnership']);

            // ── User import/export ─────────────────────────────────────────────
            Route::post('users/import',    [ImportController::class, 'import']);
            Route::get('users/export',     [UserManagementController::class, 'export']);

            // ── Users (list, create) ───────────────────────────────────────────
            Route::get('users',            [UserManagementController::class, 'index']);
            Route::post('users',           [UserManagementController::class, 'store']);

            // ── Bulk ops (before {user}) ───────────────────────────────────────
            Route::post('users/bulk/suspend',     [UserManagementController::class, 'bulkSuspend']);
            Route::post('users/bulk/unsuspend',   [UserManagementController::class, 'bulkUnsuspend']);
            Route::post('users/bulk/assign-role', [UserManagementController::class, 'bulkAssignRole']);
            Route::post('users/bulk/delete',      [UserManagementController::class, 'bulkDelete']);
            Route::post('users/stop-impersonation', [UserManagementController::class, 'stopImpersonation']);

            // ── Single user ────────────────────────────────────────────────────
            Route::get('users/{user}',         [UserManagementController::class, 'show']);
            Route::put('users/{user}',         [UserManagementController::class, 'update']);
            Route::delete('users/{user}',      [UserManagementController::class, 'destroy']);
            Route::delete('users/{user}/soft', [UserManagementController::class, 'softDelete']);

            // User actions
            Route::post('users/{user}/suspend',              [UserManagementController::class, 'suspend']);
            Route::post('users/{user}/unsuspend',            [UserManagementController::class, 'unsuspend']);
            Route::post('users/{user}/temporary-ban',        [UserManagementController::class, 'temporaryBan']);
            Route::post('users/{user}/force-password-reset', [UserManagementController::class, 'forcePasswordReset']);
            Route::post('users/{user}/remove-2fa',           [UserManagementController::class, 'removeTwoFactor']);
            Route::post('users/{user}/terminate-sessions',   [UserManagementController::class, 'terminateAllSessions']);
            Route::get('users/{user}/login-history',         [UserManagementController::class, 'loginHistory']);
            Route::post('users/{user}/impersonate',          [UserManagementController::class, 'impersonate']);

            // GDPR (admin-on-behalf-of-user)
            Route::post('users/{user}/gdpr/export',    [GdprController::class, 'export']);
            Route::post('users/{user}/gdpr/anonymise', [GdprController::class, 'anonymise']);
        });
    });
});
