<?php

namespace Susheelhbti\LaravelUserAdmin\Services;

use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;
use Susheelhbti\LaravelUserAdmin\Models\TrustedDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceService
{
    public function trustDevice(object $user, Request $request, string $deviceName = ''): array
    {
        $token      = Str::random(40);
        $fingerprint = $this->fingerprint($request);

        $device = TrustedDevice::create([
            'user_id'      => $user->id,
            'token'        => hash('sha256', $token),
            'device_name'  => $deviceName ?: ($request->header('User-Agent') ?? 'Unknown'),
            'fingerprint'  => $fingerprint,
            'ip_address'   => $request->ip(),
            'last_used_at' => now(),
            'expires_at'   => now()->addDays(config('user_admin.trusted_device_ttl_days', 90)),
        ]);

        UserAdminEvents::fire(UserAdminEvents::DEVICE_TRUSTED, [
            'user_id'   => $user->id,
            'device_id' => $device->id,
            'ip'        => $request->ip(),
        ]);

        return ['device_id' => $device->id, 'device_token' => $token];
    }

    public function isTrusted(object $user, Request $request): bool
    {
        $raw = $request->cookie('trusted_device') ?? $request->header('X-Trusted-Device');

        if (!$raw) return false;

        $hashed = hash('sha256', $raw);

        $device = TrustedDevice::where('user_id', $user->id)
            ->where('token', $hashed)
            ->where('expires_at', '>', now())
            ->first();

        if ($device) {
            // Bug #9 fix — detect fingerprint changes (browser upgrade, OS change, etc.)
            $currentFingerprint = $this->fingerprint($request);
            if ($device->fingerprint && $device->fingerprint !== $currentFingerprint) {
                UserAdminEvents::fire(UserAdminEvents::DEVICE_FINGERPRINT_CHANGE, [
                    'user_id'              => $user->id,
                    'device_id'            => $device->id,
                    'old_fingerprint'      => $device->fingerprint,
                    'new_fingerprint'      => $currentFingerprint,
                    'ip'                   => $request->ip(),
                ]);
                // Update stored fingerprint — don't block, just audit
                $device->update(['fingerprint' => $currentFingerprint]);
            }

            $device->update(['last_used_at' => now()]);
            return true;
        }

        return false;
    }

    /** Lightweight device fingerprint from available HTTP headers. */
    private function fingerprint(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->header('User-Agent', ''),
            $request->header('Accept-Language', ''),
            $request->header('Accept-Encoding', ''),
        ]));
    }

    public function revokeDevice(object $user, int $deviceId): bool
    {
        $deleted = TrustedDevice::where('user_id', $user->id)
            ->where('id', $deviceId)
            ->delete();

        if ($deleted) {
            UserAdminEvents::fire(UserAdminEvents::DEVICE_REVOKED, [
                'user_id'   => $user->id,
                'device_id' => $deviceId,
            ]);
        }

        return (bool) $deleted;
    }

    public function revokeAll(object $user): void
    {
        TrustedDevice::where('user_id', $user->id)->delete();
    }

    public function listDevices(object $user): \Illuminate\Database\Eloquent\Collection
    {
        return TrustedDevice::where('user_id', $user->id)
            ->where('expires_at', '>', now())
            ->latest('last_used_at')
            ->get();
    }
}
