<?php

namespace Susheelhbti\LaravelUserAdmin\Services;

use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;
use Susheelhbti\LaravelUserAdmin\Models\ApiKey;
use Illuminate\Support\Str;

class ApiKeyService
{
    /**
     * Create a new API key for a user.
     * Returns the raw key — shown only once, never stored in plain text.
     */
    public function create(object $user, string $name, array $scopes = [], ?\DateTime $expiresAt = null): array
    {
        $rawKey = 'uak_' . Str::random(40);

        $apiKey = ApiKey::create([
            'user_id'    => $user->id,
            'name'       => $name,
            'key'        => hash('sha256', $rawKey),
            'key_prefix' => substr($rawKey, 0, 8),   // shown in listings for identification
            'scopes'     => $scopes,
            'expires_at' => $expiresAt,
            'last_used_at' => null,
        ]);

        UserAdminEvents::fire(UserAdminEvents::API_KEY_CREATED, [
            'user_id'    => $user->id,
            'api_key_id' => $apiKey->id,
            'name'       => $name,
            'scopes'     => $scopes,
        ]);

        return ['api_key' => $apiKey, 'raw_key' => $rawKey];
    }

    /**
     * Validate a raw key, check scopes and expiry.
     * Returns the ApiKey model or null.
     */
    public function validate(string $rawKey, string $requiredScope = ''): ?ApiKey
    {
        $apiKey = ApiKey::where('key', hash('sha256', $rawKey))
            ->whereNull('revoked_at')
            ->first();

        if (!$apiKey) return null;

        if ($apiKey->expires_at && $apiKey->expires_at->isPast()) {
            UserAdminEvents::fire(UserAdminEvents::API_KEY_EXPIRED, ['api_key_id' => $apiKey->id]);
            return null;
        }

        if ($requiredScope && !empty($apiKey->scopes) && !in_array($requiredScope, $apiKey->scopes)) {
            return null;
        }

        $apiKey->update(['last_used_at' => now(), 'use_count' => $apiKey->use_count + 1]);

        return $apiKey;
    }

    /**
     * Rotate a key — revoke old one, issue new one with same config.
     */
    public function rotate(ApiKey $apiKey): array
    {
        $rawKey = 'uak_' . Str::random(40);

        $new = ApiKey::create([
            'user_id'    => $apiKey->user_id,
            'name'       => $apiKey->name,
            'key'        => hash('sha256', $rawKey),
            'key_prefix' => substr($rawKey, 0, 8),
            'scopes'     => $apiKey->scopes,
            'expires_at' => $apiKey->expires_at,
        ]);

        $apiKey->update(['revoked_at' => now()]);

        UserAdminEvents::fire(UserAdminEvents::API_KEY_ROTATED, [
            'user_id'        => $apiKey->user_id,
            'old_api_key_id' => $apiKey->id,
            'new_api_key_id' => $new->id,
        ]);

        return ['api_key' => $new, 'raw_key' => $rawKey];
    }

    public function revoke(ApiKey $apiKey): void
    {
        $apiKey->update(['revoked_at' => now()]);
        UserAdminEvents::fire(UserAdminEvents::API_KEY_REVOKED, ['api_key_id' => $apiKey->id, 'user_id' => $apiKey->user_id]);
    }

    public function listForUser(object $user): \Illuminate\Database\Eloquent\Collection
    {
        return ApiKey::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->latest()
            ->get(['id', 'name', 'key_prefix', 'scopes', 'expires_at', 'last_used_at', 'use_count', 'created_at']);
    }
}
