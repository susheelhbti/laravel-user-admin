# Laravel OTP Guard

[![Latest Version on Packagist](https://img.shields.io/packagist/v/susheelhbti/laravel-user-admin.svg)](https://packagist.org/packages/susheelhbti/laravel-user-admin)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A production-ready Laravel package that adds **OTP-based authentication** (with auto-registration) and a **full admin user-management REST API** to any Laravel application.

## Features

- 📧 Email OTP login with auto user registration
- 🔐 Laravel Sanctum token authentication
- 👥 Role & permission system (RBAC)
- 🛡️ Admin API — suspend, ban, impersonate, bulk actions
- 📊 Login history & admin audit logs
- 📤 CSV user export
- ⚙️ Fully configurable via `config/user_admin.php`
- 🧩 Zero-conflict table naming (`otpguard_*` prefix)

---

## Requirements

- PHP 8.1+
- Laravel 10 or 11
- Laravel Sanctum 3 or 4

---

## Installation

```bash
composer require susheelhbti/laravel-user-admin
```

### 1. Add the trait to your User model

```php
// app/Models/User.php

use Susheelhbti\LaravelUserAdmin\Traits\HasUserAdmin;

class User extends Authenticatable
{
    use HasUserAdmin;

    protected $casts = [
        'suspended_until'    => 'datetime',
        'last_login_at'      => 'datetime',
        'two_factor_enabled' => 'boolean',
        // ...
    ];
}
```

### 2. Publish and run migrations

```bash
php artisan vendor:publish --tag=laravel-user-admin-migrations
php artisan migrate
```

### 3. Publish config (optional)

```bash
php artisan vendor:publish --tag=laravel-user-admin-config
```

### 4. Seed default roles & permissions (optional)

```bash
php artisan db:seed --class="Susheelhbti\\LaravelUserAdmin\\Database\\Seeders\\LaravelUserAdminSeeder"
```

Or call it from your `DatabaseSeeder`:

```php
$this->call(\Susheelhbti\LaravelUserAdmin\Database\Seeders\LaravelUserAdminSeeder::class);
```

### 5. Schedule OTP cleanup

In `app/Console/Kernel.php` (Laravel 10) or `routes/console.php` (Laravel 11):

```php
// Laravel 11 — routes/console.php
Schedule::command('user-admin:clean-otps')->everyFifteenMinutes();
```

---

## Configuration

After publishing, edit `config/user_admin.php`:

```php
return [
    'register_routes'   => true,       // Set false to define your own routes
    'route_prefix'      => 'api',
    'route_middleware'  => [],

    'otp' => [
        'expires_in_minutes' => 5,
        'max_attempts'       => 3,
        'rate_limit_count'   => 3,
        'rate_limit_minutes' => 5,
        'code_length'        => 6,
    ],

    'user_model'        => \App\Models\User::class,
    'default_role_slug' => 'user',
    'admin_role_slug'   => 'admin',
    'token_name'        => 'auth_token',
    'auto_register'     => true,
    'per_page'          => 15,
];
```

---

## API Endpoints

### Authentication

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/api/auth/otp/send` | — | Send OTP to email |
| POST | `/api/auth/otp/verify` | — | Verify OTP, receive token |
| POST | `/api/auth/logout` | Bearer | Revoke current token |
| GET | `/api/auth/me` | Bearer | Get current user |

### Admin — Users

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/users` | List users (filters: `status`, `role`, `search`, `sort_by`, `sort_direction`) |
| POST | `/api/admin/users` | Create user |
| GET | `/api/admin/users/{id}` | Get user |
| PUT | `/api/admin/users/{id}` | Update user |
| DELETE | `/api/admin/users/{id}` | Permanently delete user |
| DELETE | `/api/admin/users/{id}/soft` | Soft delete user |
| GET | `/api/admin/users/export` | Download CSV |

### Admin — User Actions

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/admin/users/{id}/suspend` | Suspend user |
| POST | `/api/admin/users/{id}/unsuspend` | Unsuspend user |
| POST | `/api/admin/users/{id}/temporary-ban` | Temporary ban (requires `suspended_until`) |
| POST | `/api/admin/users/{id}/force-password-reset` | Force password reset |
| POST | `/api/admin/users/{id}/remove-2fa` | Remove 2FA |
| POST | `/api/admin/users/{id}/terminate-sessions` | Revoke all tokens |
| GET | `/api/admin/users/{id}/login-history` | Login history |
| POST | `/api/admin/users/{id}/impersonate` | Impersonate user |
| POST | `/api/admin/users/stop-impersonation` | Stop impersonation |

### Admin — Bulk Operations

| Method | Endpoint | Body |
|--------|----------|------|
| POST | `/api/admin/users/bulk/suspend` | `{ user_ids: [...] }` |
| POST | `/api/admin/users/bulk/unsuspend` | `{ user_ids: [...] }` |
| POST | `/api/admin/users/bulk/assign-role` | `{ user_ids: [...], role: "moderator" }` |
| POST | `/api/admin/users/bulk/delete` | `{ user_ids: [...] }` |

### Admin — Stats & Logs

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/statistics` | User stats |
| GET | `/api/admin/admin-logs` | Admin audit log |

---

## Usage Examples

### Send OTP

```bash
curl -X POST http://your-app.test/api/auth/otp/send \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com"}'
```

Response:
```json
{ "message": "OTP sent successfully.", "otp_id": 42 }
```

### Verify OTP

```bash
curl -X POST http://your-app.test/api/auth/otp/verify \
  -H "Content-Type: application/json" \
  -d '{"otp_id": 42, "code": "381920"}'
```

Response:
```json
{
  "message": "Login successful.",
  "token": "1|abc123...",
  "user": { "id": 1, "email": "user@example.com", "roles": ["user"] }
}
```

### Suspend a user (Admin)

```bash
curl -X POST http://your-app.test/api/admin/users/5/suspend \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: application/json" \
  -d '{"suspended_until":"2026-07-01T00:00:00Z","reason":"Terms violation","notify":true}'
```

---

## Middleware

The package registers two middleware aliases:

- `user-admin.admin` — Requires the user to have the admin role (configurable via `admin_role_slug`)
- `user-admin.role:{slug}` — Requires the user to have a specific role

You can apply them to your own routes:

```php
Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth:sanctum', 'user-admin.admin']);
```

---

## Disabling Built-in Routes

Set `register_routes => false` in the config and define your own:

```php
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth\OtpController;
use Susheelhbti\LaravelUserAdmin\Http\Controllers\Admin\UserManagementController;

Route::post('login/send', [OtpController::class, 'sendOtp']);
Route::post('login/verify', [OtpController::class, 'verifyOtp']);
```

---

## Customising Email Views

```bash
php artisan vendor:publish --tag=laravel-user-admin-views
```

Views will be published to `resources/views/vendor/laravel-user-admin/emails/`.

---

## License

MIT © Susheel Kumar
