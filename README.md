<div align="center">

# 🔐 laravel-user-admin

**OTP-based authentication + full admin user-management REST API for Laravel**

[![Packagist](https://img.shields.io/packagist/v/susheelhbti/laravel-user-admin.svg)](https://packagist.org/packages/susheelhbti/laravel-user-admin)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-brightgreen)](https://php.net)
[![Laravel](https://img.shields.io/badge/laravel-10%20%7C%2011-red)](https://laravel.com)

---

**Author: Susheel Kumar** — available for **freelance & full-time** roles.
📧 [susheelhbti@gmail.com](mailto:susheelhbti@gmail.com)

</div>

## ✨ Features

- 📧 Email OTP login with auto user registration
- 🔐 Sanctum access token + **refresh token rotation** with reuse detection
- 👥 Role & permission system (RBAC)
- 🔒 **TOTP two-factor authentication** + backup codes
- 📱 **Trusted device management** — skip 2FA on trusted devices
- 🛡️ Admin API — suspend, ban, impersonate, bulk actions
- 📊 Login history & admin audit logs with **Request IDs**
- 📤 CSV user export + 📥 **Bulk CSV/JSON import**
- 🗑️ **Account deletion with grace period**
- 🎉 **30+ named events** for Slack, webhooks, Datadog
- ❤️ **Health check** endpoint
- ⚙️ Fully configurable

---

## Requirements

- PHP 8.1+, Laravel 10 or 11, Laravel Sanctum 3+
- *(Optional)* `pragmarx/google2fa` for TOTP 2FA

---

## Installation

```bash
composer require susheelhbti/laravel-user-admin
```

Add trait to your User model:

```php
use Susheelhbti\LaravelUserAdmin\Traits\HasUserAdmin;

class User extends Authenticatable
{
    use HasUserAdmin;

    protected $casts = [
        'suspended_until'       => 'datetime',
        'last_login_at'         => 'datetime',
        'two_factor_enabled'    => 'boolean',
        'deletion_requested_at' => 'datetime',
        'deletion_scheduled_at' => 'datetime',
    ];
}
```

Run migrations:

```bash
php artisan vendor:publish --tag=laravel-user-admin-migrations
php artisan migrate
```

Seed roles and permissions:

```bash
php artisan db:seed --class="Susheelhbti\\LaravelUserAdmin\\Database\\Seeders\\LaravelUserAdminSeeder"
```

Schedule commands (Laravel 11 `routes/console.php`):

```php
Schedule::command('user-admin:clean-otps')->everyFifteenMinutes();
Schedule::command('user-admin:purge-accounts')->daily();
```

---

## API Endpoints

### General
| GET | `/api/health` | DB status + version |

### Auth
| POST | `/api/auth/otp/send` | Send OTP |
| POST | `/api/auth/otp/verify` | Verify OTP → access + refresh tokens |
| POST | `/api/auth/logout` | Revoke all tokens |
| GET  | `/api/auth/me` | Current user |
| POST | `/api/auth/token/refresh` | Rotate tokens |

### 2FA
| GET  | `/api/auth/2fa/setup` | Generate TOTP secret + QR |
| POST | `/api/auth/2fa/confirm` | Enable 2FA |
| POST | `/api/auth/2fa/disable` | Disable 2FA |
| POST | `/api/auth/2fa/backup-codes/regenerate` | New backup codes |

**2FA login flow** — on first verify when 2FA is enabled:
```json
{ "requires_2fa": true, "otp_id": 42, "message": "Two-factor authentication required." }
```
Re-submit with `totp_token` OR `backup_code` — no separate endpoint needed.

### Trusted Devices
| GET    | `/api/auth/devices` | List |
| POST   | `/api/auth/devices/trust` | Mark trusted |
| DELETE | `/api/auth/devices/{id}` | Revoke one |
| DELETE | `/api/auth/devices` | Revoke all |

### Account Deletion
| POST | `/api/auth/account/request-deletion` | Schedule deletion |
| POST | `/api/auth/account/cancel-deletion` | Cancel |

### Admin — Users
| GET    | `/api/admin/users` | List (filter: status, role, search) |
| POST   | `/api/admin/users` | Create |
| GET    | `/api/admin/users/{id}` | Detail |
| PUT    | `/api/admin/users/{id}` | Update |
| DELETE | `/api/admin/users/{id}` | Hard delete |
| DELETE | `/api/admin/users/{id}/soft` | Soft delete |
| GET    | `/api/admin/users/export` | CSV download |
| POST   | `/api/admin/users/import` | Bulk CSV/JSON import |

### Admin — Actions
| POST | `/api/admin/users/{id}/suspend` | Suspend |
| POST | `/api/admin/users/{id}/unsuspend` | Unsuspend |
| POST | `/api/admin/users/{id}/temporary-ban` | Temp ban |
| POST | `/api/admin/users/{id}/force-password-reset` | Force reset |
| POST | `/api/admin/users/{id}/remove-2fa` | Remove 2FA |
| POST | `/api/admin/users/{id}/terminate-sessions` | Kill sessions |
| GET  | `/api/admin/users/{id}/login-history` | Login log |
| POST | `/api/admin/users/{id}/impersonate` | Impersonate |
| POST | `/api/admin/users/stop-impersonation` | Stop |

### Admin — Bulk
| POST | `/api/admin/users/bulk/suspend` | Bulk suspend |
| POST | `/api/admin/users/bulk/unsuspend` | Bulk unsuspend |
| POST | `/api/admin/users/bulk/assign-role` | Bulk role |
| POST | `/api/admin/users/bulk/delete` | Bulk delete |

### Admin — Stats
| GET | `/api/admin/statistics` | Counts by status/role |
| GET | `/api/admin/admin-logs` | Audit log |

---

## 🎉 Event System

```php
use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;
use Illuminate\Support\Facades\Event;

Event::listen(UserAdminEvents::LOGIN_SUCCESS, function (string $e, array $data) {
    logger("Login: {$data['email']} from {$data['ip']}");
});

Event::listen(UserAdminEvents::TOKEN_REUSE_DETECTED, function (string $e, array $data) {
    SlackAlert::send("Token reuse attack for user #{$data['user_id']}!");
});

Event::listen(UserAdminEvents::USER_CREATED, function (string $e, array $data) {
    CrmService::createContact($data['email']);
});
```

Every payload contains `event`, `fired_at`, `request_id`.

**All events:** `OTP_SENT`, `OTP_VERIFIED`, `OTP_FAILED`, `LOGIN_SUCCESS`, `LOGIN_FAILED`, `LOGOUT`, `TOKEN_REFRESHED`, `TOKEN_REUSE_DETECTED`, `TFA_ENABLED`, `TFA_DISABLED`, `TFA_FAILED`, `DEVICE_TRUSTED`, `DEVICE_REVOKED`, `SESSIONS_TERMINATED`, `USER_CREATED`, `USER_UPDATED`, `USER_DELETED`, `USER_SUSPENDED`, `USER_UNSUSPENDED`, `ACCOUNT_DELETION_REQUESTED`, `ACCOUNT_DELETION_COMPLETED`, `ACCOUNT_DELETION_CANCELLED`, `ADMIN_ACTION`, `IMPERSONATION_STARTED`, `IMPERSONATION_STOPPED`, `BULK_USERS_IMPORTED`, `BULK_USERS_SUSPENDED`, `BULK_USERS_DELETED`, `BULK_ROLE_ASSIGNED`, `EMAIL_SENT`, `EMAIL_FAILED`

---

## Middleware

```php
Route::get('/dashboard', fn() => ...)->middleware(['auth:sanctum', 'user-admin.admin']);
Route::get('/mod-panel', fn() => ...)->middleware(['auth:sanctum', 'user-admin.role:moderator']);
```

---

## Optional TOTP 2FA

```bash
composer require pragmarx/google2fa
```

No extra configuration — auto-enabled once installed.

---

## Artisan Commands

| Command | Schedule |
|---------|----------|
| `user-admin:clean-otps` | Every 15 min |
| `user-admin:purge-accounts` | Daily |

---

## License

MIT © [Susheel Kumar](mailto:susheelhbti@gmail.com)
