<?php

namespace Susheelhbti\LaravelUserAdmin\Services;

use Susheelhbti\LaravelUserAdmin\Models\LoginHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Full analytics dashboard payload.
     */
    public function dashboard(): array
    {
        return Cache::remember('user_admin.analytics.dashboard', 300, function () {
            $model = config('user_admin.user_model', \App\Models\User::class);

            return [
                'users'          => $this->userStats($model),
                'activity'       => $this->activityStats(),
                'devices'        => $this->deviceStats(),
                'peak_hours'     => $this->peakHoursHeatmap(),
                'failed_logins'  => $this->failedLoginTrend(),
            ];
        });
    }

    public function userStats(string $model): array
    {
        $now = now();
        return [
            'total'           => $model::count(),
            'active'          => $model::where('status', 'active')->count(),
            'suspended'       => $model::where('status', 'suspended')->count(),
            'archived'        => $model::where('status', 'archived')->count(),
            'dau'             => $model::whereDate('last_login_at', today())->count(),
            'mau'             => $model::where('last_login_at', '>=', $now->copy()->subDays(30))->count(),
            'new_today'       => $model::whereDate('created_at', today())->count(),
            'new_this_month'  => $model::where('created_at', '>=', $now->copy()->startOfMonth())->count(),
        ];
    }

    /** Login counts grouped by hour of day (0–23) — heatmap data. */
    public function peakHoursHeatmap(): array
    {
        $rows = LoginHistory::select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('hour')
            ->pluck('count', 'hour')
            ->toArray();

        $heatmap = [];
        for ($h = 0; $h < 24; $h++) {
            $heatmap[$h] = $rows[$h] ?? 0;
        }

        return $heatmap;
    }

    /** Top 10 user agents (browsers/OS) from login history. */
    public function deviceStats(): array
    {
        return LoginHistory::select('user_agent', DB::raw('COUNT(*) as count'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('user_agent')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['user_agent' => $r->user_agent, 'count' => $r->count])
            ->toArray();
    }

    /** Login count per day for the last 30 days. */
    public function activityStats(): array
    {
        return LoginHistory::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(DISTINCT user_id) as unique_users'),
                DB::raw('COUNT(*) as total_logins')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /** Failed OTP attempts per day. */
    public function failedLoginTrend(): array
    {
        // AdminLog keeps failed attempts
        return \Susheelhbti\LaravelUserAdmin\Models\AdminLog::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count')
            )
            ->where('action', 'LIKE', '%fail%')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    /**
     * Prometheus-compatible metrics text output.
     */
    public function prometheusMetrics(): string
    {
        $model = config('user_admin.user_model', \App\Models\User::class);
        $stats = $this->userStats($model);

        $lines = [
            '# HELP user_admin_users_total Total registered users',
            '# TYPE user_admin_users_total gauge',
            "user_admin_users_total {$stats['total']}",
            '',
            '# HELP user_admin_users_active Active users',
            '# TYPE user_admin_users_active gauge',
            "user_admin_users_active {$stats['active']}",
            '',
            '# HELP user_admin_users_suspended Suspended users',
            '# TYPE user_admin_users_suspended gauge',
            "user_admin_users_suspended {$stats['suspended']}",
            '',
            '# HELP user_admin_dau Daily active users',
            '# TYPE user_admin_dau gauge',
            "user_admin_dau {$stats['dau']}",
            '',
            '# HELP user_admin_mau Monthly active users',
            '# TYPE user_admin_mau gauge',
            "user_admin_mau {$stats['mau']}",
        ];

        return implode("\n", $lines) . "\n";
    }
}
