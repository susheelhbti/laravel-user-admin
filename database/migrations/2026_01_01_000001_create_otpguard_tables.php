<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Roles
        Schema::create('otpguard_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Permissions
        Schema::create('otpguard_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('group')->nullable();
            $table->timestamps();
        });

        // Pivot: role ↔ user
        Schema::create('otpguard_role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('otpguard_roles')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Pivot: permission ↔ user
        Schema::create('otpguard_permission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permission_id')->constrained('otpguard_permissions')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Pivot: role ↔ permission
        Schema::create('otpguard_role_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('otpguard_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('otpguard_permissions')->cascadeOnDelete();
            $table->timestamps();
        });

        // OTPs
        Schema::create('otpguard_otps', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('code');
            $table->string('session_id')->index();
            $table->timestamp('expires_at');
            $table->integer('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        // Login histories
        Schema::create('otpguard_login_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('ip_address');
            $table->string('user_agent');
            $table->string('location')->nullable();
            $table->timestamps();
        });

        // Admin logs
        Schema::create('otpguard_admin_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id');
            $table->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->foreign('target_user_id')->references('id')->on('users')->nullOnDelete();
            $table->string('action');
            $table->json('details')->nullable();
            $table->string('ip_address');
            $table->timestamps();
        });

        // Extra columns on users table
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('active');
            }
            if (!Schema::hasColumn('users', 'suspended_until')) {
                $table->timestamp('suspended_until')->nullable();
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'last_login_ip')) {
                $table->string('last_login_ip')->nullable();
            }
            if (!Schema::hasColumn('users', 'two_factor_enabled')) {
                $table->boolean('two_factor_enabled')->default(false);
            }
            if (!Schema::hasColumn('users', 'two_factor_secret')) {
                $table->string('two_factor_secret')->nullable();
            }
            if (!Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otpguard_admin_logs');
        Schema::dropIfExists('otpguard_login_histories');
        Schema::dropIfExists('otpguard_otps');
        Schema::dropIfExists('otpguard_role_permission');
        Schema::dropIfExists('otpguard_permission_user');
        Schema::dropIfExists('otpguard_role_user');
        Schema::dropIfExists('otpguard_permissions');
        Schema::dropIfExists('otpguard_roles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'status', 'suspended_until', 'last_login_at',
                'last_login_ip', 'two_factor_enabled', 'two_factor_secret',
                'password_changed_at', 'deleted_at',
            ]);
        });
    }
};
