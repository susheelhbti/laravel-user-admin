# Migration Guide

## Upgrading from v1 to v2

### What's new in v2

- Refresh token rotation + reuse detection
- TOTP two-factor authentication + backup codes
- Trusted device management
- Account deletion with grace period
- Bulk CSV/JSON user import
- 30+ named events
- Health check endpoint
- Request IDs on all admin log entries

---

### Step 1 — Update the package

```bash
composer update susheelhbti/laravel-user-admin
```

---

### Step 2 — Run new migrations

```bash
php artisan vendor:publish --tag=laravel-user-admin-migrations
php artisan migrate
```

New tables added:
- `user_admin_refresh_tokens`
- `user_admin_trusted_devices`

New columns on `users`:
- `two_factor_backup_codes`
- `deletion_requested_at`
- `deletion_scheduled_at`

New column on `otpguard_admin_logs`:
- `request_id`

---

### Step 3 — Update User model casts

```php
protected $casts = [
    // existing
    'suspended_until'       => 'datetime',
    'last_login_at'         => 'datetime',
    'two_factor_enabled'    => 'boolean',
    // new in v2
    'deletion_requested_at' => 'datetime',
    'deletion_scheduled_at' => 'datetime',
];
```

---

### Step 4 — Update scheduled commands

Add `user-admin:purge-accounts` to your schedule:

```php
// Laravel 11 — routes/console.php
Schedule::command('user-admin:clean-otps')->everyFifteenMinutes();
Schedule::command('user-admin:purge-accounts')->daily();   // NEW
```

---

### Step 5 — Token response shape change

`POST /api/auth/otp/verify` now returns:

```json
{
  "access_token":  "...",
  "refresh_token": "...",
  "token_type":    "Bearer",
  "token":         "..."   // kept for backward compatibility
}
```

The legacy `token` field is still present — update clients when ready to use `access_token` + `refresh_token`.

---

### Step 6 — (Optional) Enable TOTP 2FA

```bash
composer require pragmarx/google2fa
```

No config change needed.

---

### Breaking changes

| Area | v1 | v2 |
|------|----|----|
| Middleware alias | `otpguard.admin` | `user-admin.admin` |
| Middleware alias | `otpguard.role` | `user-admin.role` |
| Config key | `config('otpguard.*')` | `config('user_admin.*')` |
| Config file | `config/otpguard.php` | `config/user_admin.php` |
| Artisan command | `otpguard:clean-otps` | `user-admin:clean-otps` |
| Trait | `HasOtpGuard` | `HasUserAdmin` |
| Logout | revokes current token | revokes ALL tokens + refresh tokens |
