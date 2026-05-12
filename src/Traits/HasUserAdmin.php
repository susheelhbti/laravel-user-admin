<?php

namespace Susheelhbti\LaravelUserAdmin\Traits;

use Susheelhbti\LaravelUserAdmin\Models\Role;
use Susheelhbti\LaravelUserAdmin\Models\Permission;
use Susheelhbti\LaravelUserAdmin\Models\LoginHistory;
use Susheelhbti\LaravelUserAdmin\Models\AdminLog;

/**
 * Add this trait to your User model:
 *
 *   use Susheelhbti\LaravelUserAdmin\Traits\HasUserAdmin;
 *
 * Also add these columns to your users table (the package migration handles this):
 *   status, suspended_until, last_login_at, last_login_ip,
 *   two_factor_enabled, two_factor_secret, password_changed_at
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
