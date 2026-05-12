<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Refresh tokens ─────────────────────────────────────────────────────
        Schema::create('user_admin_refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('access_token_id')->nullable();
            $table->string('token', 64)->unique();   // SHA-256 of the raw token
            $table->uuid('family');                  // token rotation family
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
            $table->index('family');
        });

        // ── Trusted devices ────────────────────────────────────────────────────
        Schema::create('user_admin_trusted_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('device_name')->nullable();
            $table->string('fingerprint', 64)->nullable();  // SHA-256 of UA+Accept-Language+Accept-Encoding
            $table->string('ip_address')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('user_id');
        });

        // ── 2FA + account deletion columns on users ────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'two_factor_backup_codes')) {
                $table->text('two_factor_backup_codes')->nullable()->after('two_factor_secret');
            }
            if (!Schema::hasColumn('users', 'deletion_requested_at')) {
                $table->timestamp('deletion_requested_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'deletion_scheduled_at')) {
                $table->timestamp('deletion_scheduled_at')->nullable();
            }
        });

        // ── Add request_id to admin logs ───────────────────────────────────────
        Schema::table('otpguard_admin_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('otpguard_admin_logs', 'request_id')) {
                $table->string('request_id')->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_admin_trusted_devices');
        Schema::dropIfExists('user_admin_refresh_tokens');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_backup_codes', 'deletion_requested_at', 'deletion_scheduled_at']);
        });

        Schema::table('otpguard_admin_logs', function (Blueprint $table) {
            $table->dropColumn('request_id');
        });
    }
};
