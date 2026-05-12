<?php

namespace Susheelhbti\LaravelUserAdmin\Services;

use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GeoService
{
    /**
     * Full check: blocked country, IP reputation, distance anomaly.
     * Returns ['allowed' => bool, 'reason' => string|null, 'geo' => array]
     */
    public function checkLogin(string $ip, int $userId, ?string $lastIp, ?\DateTime $lastLoginAt): array
    {
        $geo = $this->lookup($ip);

        // 1. Blocked country
        $blocked = config('user_admin.geo.blocked_countries', []);
        if (!empty($geo['country_code']) && in_array(strtoupper($geo['country_code']), array_map('strtoupper', $blocked))) {
            UserAdminEvents::fire(UserAdminEvents::LOGIN_BLOCKED_GEO, [
                'user_id'      => $userId,
                'ip'           => $ip,
                'country_code' => $geo['country_code'],
            ]);
            return ['allowed' => false, 'reason' => 'geo_blocked', 'geo' => $geo];
        }

        // 2. IP reputation
        if (config('user_admin.geo.check_ip_reputation', false)) {
            $rep = $this->checkReputation($ip);
            if (!$rep['safe']) {
                UserAdminEvents::fire(UserAdminEvents::LOGIN_BLOCKED_IP, [
                    'user_id' => $userId,
                    'ip'      => $ip,
                    'reason'  => $rep['reason'],
                ]);
                return ['allowed' => false, 'reason' => 'ip_reputation', 'geo' => $geo];
            }
        }

        // 3. Distance anomaly (impossible travel)
        if ($lastIp && $lastLoginAt && config('user_admin.geo.anomaly_detection', true)) {
            $anomaly = $this->detectAnomaly($ip, $lastIp, $lastLoginAt);
            if ($anomaly) {
                UserAdminEvents::fire(UserAdminEvents::LOGIN_ANOMALY_DETECTED, [
                    'user_id'            => $userId,
                    'ip'                 => $ip,
                    'last_ip'            => $lastIp,
                    'distance_km'        => $anomaly['distance_km'],
                    'hours_elapsed'      => $anomaly['hours_elapsed'],
                    'speed_km_per_hour'  => $anomaly['speed_km_per_hour'],
                ]);

                if (config('user_admin.geo.anomaly_action', 'warn') === 'block') {
                    return ['allowed' => false, 'reason' => 'impossible_travel', 'geo' => $geo];
                }
            }
        }

        return ['allowed' => true, 'reason' => null, 'geo' => $geo];
    }

    /**
     * GeoIP lookup via ip-api.com (free, no key required).
     * Cached for 24 hours per IP.
     */
    public function lookup(string $ip): array
    {
        if ($this->isPrivateIp($ip)) {
            return ['country_code' => null, 'country' => 'Local', 'city' => null, 'lat' => null, 'lon' => null];
        }

        return Cache::remember("user_admin.geo.{$ip}", 86400, function () use ($ip) {
            try {
                $resp = Http::timeout(3)->get("http://ip-api.com/json/{$ip}?fields=status,country,countryCode,city,lat,lon");
                if ($resp->ok() && $resp->json('status') === 'success') {
                    return [
                        'country_code' => $resp->json('countryCode'),
                        'country'      => $resp->json('country'),
                        'city'         => $resp->json('city'),
                        'lat'          => $resp->json('lat'),
                        'lon'          => $resp->json('lon'),
                    ];
                }
            } catch (\Throwable) {}

            return ['country_code' => null, 'country' => null, 'city' => null, 'lat' => null, 'lon' => null];
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function checkReputation(string $ip): array
    {
        // Check StopForumSpam (free, no key)
        return Cache::remember("user_admin.iprep.{$ip}", 3600, function () use ($ip) {
            try {
                $resp = Http::timeout(3)->get("https://api.stopforumspam.org/api?ip={$ip}&json");
                if ($resp->ok()) {
                    $data = $resp->json();
                    if (($data['ip']['appears'] ?? 0) > 0) {
                        return ['safe' => false, 'reason' => 'stopforumspam'];
                    }
                }
            } catch (\Throwable) {}

            return ['safe' => true, 'reason' => null];
        });
    }

    private function detectAnomaly(string $currentIp, string $lastIp, \DateTime $lastLoginAt): ?array
    {
        $current = $this->lookup($currentIp);
        $last    = $this->lookup($lastIp);

        if (!$current['lat'] || !$last['lat']) return null;

        $distanceKm  = $this->haversine($last['lat'], $last['lon'], $current['lat'], $current['lon']);
        $hoursElapsed = max(0.01, now()->diffInMinutes($lastLoginAt) / 60);
        $speedKmh     = $distanceKm / $hoursElapsed;

        $threshold = config('user_admin.geo.anomaly_speed_kmh', 900); // ~speed of commercial aircraft

        if ($speedKmh > $threshold) {
            return [
                'distance_km'       => round($distanceKm),
                'hours_elapsed'     => round($hoursElapsed, 2),
                'speed_km_per_hour' => round($speedKmh),
            ];
        }

        return null;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r    = 6371; // Earth radius km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $r * 2 * asin(sqrt($a));
    }

    private function isPrivateIp(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
