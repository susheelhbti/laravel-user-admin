<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Teams ──────────────────────────────────────────────────────────────
        Schema::create('user_admin_teams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('settings')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('user_admin_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('user_admin_teams')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('role')->default('member'); // owner, admin, lead, member
            $table->unique(['team_id', 'user_id']);
            $table->timestamps();
        });

        // ── API Keys ───────────────────────────────────────────────────────────
        Schema::create('user_admin_api_keys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('key', 64)->unique();        // SHA-256 of raw key
            $table->string('key_prefix', 10);           // first 8 chars, shown in listings
            $table->json('scopes')->nullable();
            $table->unsignedBigInteger('use_count')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at']);
        });

        // ── Webhooks ───────────────────────────────────────────────────────────
        Schema::create('user_admin_webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('url');
            $table->string('secret', 64);
            $table->json('events');                     // ['*'] for all events
            $table->boolean('active')->default(true);
            $table->string('description')->nullable();
            $table->unsignedBigInteger('success_count')->default(0);
            $table->unsignedBigInteger('fail_count')->default(0);
            $table->timestamps();
        });

        Schema::create('user_admin_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endpoint_id')->constrained('user_admin_webhook_endpoints')->cascadeOnDelete();
            $table->string('event');
            $table->json('payload')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->string('status')->default('pending'); // pending, delivered, failed, retrying, exhausted
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamps();
            $table->index(['endpoint_id', 'status']);
        });

        // ── GDPR Consent ───────────────────────────────────────────────────────
        Schema::create('user_admin_gdpr_consents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('consent_type');              // e.g. 'marketing', 'analytics', 'data_processing'
            $table->boolean('granted')->default(false);
            $table->string('ip_address')->nullable();
            $table->unique(['user_id', 'consent_type']);
            $table->timestamps();
        });

        // ── Security Questions ─────────────────────────────────────────────────
        Schema::create('user_admin_security_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('question');
            $table->string('answer');    // bcrypt hashed
            $table->timestamps();
            $table->index('user_id');
        });

        // ── Extra user columns ─────────────────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                ['tags',              'json',    null],
                ['metadata',          'json',    null],
                ['profile_score',     'tinyint', 0],
                ['account_expires_at','timestamp', null],
                ['failed_login_count','unsignedSmallInteger', 0],
                ['last_failed_login_at', 'timestamp', null],
            ] as [$col, $type, $default]) {
                if (!Schema::hasColumn('users', $col)) {
                    match($type) {
                        'json'      => $table->json($col)->nullable(),
                        'tinyint'   => $table->tinyInteger($col)->default($default),
                        'unsignedSmallInteger' => $table->unsignedSmallInteger($col)->default($default),
                        'timestamp' => $table->timestamp($col)->nullable(),
                    };
                }
            }

            // Bug #8 fix — index for conditional auth rule lookups
            if (!Schema::hasIndex('users', 'users_failed_login_count_last_failed_login_at_index')) {
                $table->index(['failed_login_count', 'last_failed_login_at'], 'users_failed_login_count_last_failed_login_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_admin_security_questions');
        Schema::dropIfExists('user_admin_gdpr_consents');
        Schema::dropIfExists('user_admin_webhook_deliveries');
        Schema::dropIfExists('user_admin_webhook_endpoints');
        Schema::dropIfExists('user_admin_api_keys');
        Schema::dropIfExists('user_admin_team_members');
        Schema::dropIfExists('user_admin_teams');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tags', 'metadata', 'profile_score', 'account_expires_at', 'failed_login_count', 'last_failed_login_at']);
        });
    }
};
