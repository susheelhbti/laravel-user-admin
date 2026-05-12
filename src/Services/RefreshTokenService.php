<?php

namespace Susheelhbti\LaravelUserAdmin\Services;

use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;
use Susheelhbti\LaravelUserAdmin\Models\RefreshToken;
use Illuminate\Support\Str;

class RefreshTokenService
{
    /**
     * Issue a new refresh token for the given user & access token.
     */
    public function issue(object $user, string $accessTokenId): string
    {
        $raw = Str::random(64);

        RefreshToken::create([
            'user_id'         => $user->id,
            'access_token_id' => $accessTokenId,
            'token'           => hash('sha256', $raw),
            'family'          => Str::uuid()->toString(),
            'expires_at'      => now()->addDays(config('user_admin.refresh_token.ttl_days', 30)),
        ]);

        return $raw;
    }

    /**
     * Rotate: validate the raw token, revoke it, issue a fresh pair.
     * Returns ['access_token', 'refresh_token'] or throws.
     */
    public function rotate(string $rawToken, object $user): array
    {
        $hashed  = hash('sha256', $rawToken);
        $stored  = RefreshToken::where('token', $hashed)->where('user_id', $user->id)->first();

        if (!$stored) {
            throw new \RuntimeException('Refresh token not found.');
        }

        // Reuse detection: already revoked → revoke whole family
        if ($stored->revoked_at) {
            RefreshToken::where('family', $stored->family)->update(['revoked_at' => now()]);

            UserAdminEvents::fire(UserAdminEvents::TOKEN_REUSE_DETECTED, [
                'user_id' => $user->id,
                'family'  => $stored->family,
                'ip'      => request()->ip(),
            ]);

            throw new \RuntimeException('Refresh token reuse detected. All sessions have been revoked.');
        }

        if ($stored->expires_at->isPast()) {
            throw new \RuntimeException('Refresh token has expired.');
        }

        // Revoke current
        $stored->update(['revoked_at' => now()]);
        $stored->accessToken?->delete();

        // Issue new Sanctum token
        $newAccess  = $user->createToken(config('user_admin.token_name', 'auth_token'));
        $newRefresh = Str::random(64);

        RefreshToken::create([
            'user_id'         => $user->id,
            'access_token_id' => $newAccess->accessToken->id,
            'token'           => hash('sha256', $newRefresh),
            'family'          => $stored->family,    // keep same family
            'expires_at'      => now()->addDays(config('user_admin.refresh_token.ttl_days', 30)),
        ]);

        UserAdminEvents::fire(UserAdminEvents::TOKEN_REFRESHED, [
            'user_id' => $user->id,
            'ip'      => request()->ip(),
        ]);

        return [
            'access_token'  => $newAccess->plainTextToken,
            'refresh_token' => $newRefresh,
        ];
    }

    /** Revoke all refresh tokens for a user. */
    public function revokeAll(object $user): void
    {
        RefreshToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }
}
