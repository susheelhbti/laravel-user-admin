<?php

namespace Susheelhbti\LaravelUserAdmin\Traits;

use Susheelhbti\LaravelUserAdmin\Models\Role;
use Susheelhbti\LaravelUserAdmin\Models\Permission;
use Susheelhbti\LaravelUserAdmin\Models\LoginHistory;
use Susheelhbti\LaravelUserAdmin\Models\AdminLog;
use Susheelhbti\LaravelUserAdmin\Models\TrustedDevice;
use Susheelhbti\LaravelUserAdmin\Models\RefreshToken;

/**
 * Add this trait to your User model:
 *
 *   use Susheelhbti\LaravelUserAdmin\Traits\HasUserAdmin;
 *
 * Add these casts to your User model:
 *
 *   protected $casts = [
 *       'suspended_until'       => 'datetime',
 *       'last_login_at'         => 'datetime',
 *       'two_factor_enabled'    => 'boolean',
 *       'deletion_requested_at' => 'datetime',
 *       'deletion_scheduled_at' => 'datetime',
 *       'account_expires_at'    => 'datetime',
 *       'tags'                  => 'array',
 *       'metadata'              => 'array',
 *   ];
 */
trait HasUserAdmin
{
    // ── Relationships ─────────────────────────────────────────────────────────

    public function otpRoles()
    {
        return $this->belongsToMany(Role::class, 'otpguard_role_user', 'user_id', 'role_id');
    }

    public function otpPermissions()
    {
        return $this->belongsToMany(Permission::class, 'otpguard_permission_user', 'user_id', 'permission_id');
    }

    public function loginHistories()
    {
        return $this->hasMany(LoginHistory::class);
    }

    public function adminLogs()
    {
        return $this->hasMany(AdminLog::class, 'target_user_id');
    }

    public function trustedDevices()
    {
        return $this->hasMany(TrustedDevice::class);
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    // ── Role helpers ──────────────────────────────────────────────────────────

    public function hasOtpRole(string $role): bool
    {
        return $this->otpRoles->contains('slug', $role);
    }

    public function hasOtpPermission(string $permission): bool
    {
        return $this->otpPermissions->contains('slug', $permission);
    }

    public function isOtpAdmin(): bool
    {
        return $this->hasOtpRole(config('user_admin.admin_role_slug', 'admin'));
    }

    // ── Status helpers ────────────────────────────────────────────────────────

    public function isSuspended(): bool
    {
        if ($this->status === 'suspended') {
            if ($this->suspended_until && $this->suspended_until->isPast()) {
                $this->update(['status' => 'active', 'suspended_until' => null]);
                return false;
            }
            return true;
        }
        return false;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return (bool) $this->two_factor_enabled;
    }

    public function isPendingDeletion(): bool
    {
        return !is_null($this->deletion_requested_at);
    }

    // ── Bug #3 fix — rememberToken() required by Sanctum ─────────────────────

    public function rememberToken(): mixed
    {
        return $this->remember_token ?? null;
    }

    // ── Login tracking ────────────────────────────────────────────────────────

    public function recordLogin(string $ip, string $userAgent): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ]);

        $this->loginHistories()->create([
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
