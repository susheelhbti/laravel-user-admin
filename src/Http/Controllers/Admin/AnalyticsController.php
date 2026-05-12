<?php
namespace Susheelhbti\LaravelUserAdmin\Http\Controllers\Admin;
use Illuminate\Routing\Controller;
use Susheelhbti\LaravelUserAdmin\Services\AnalyticsService;

class AnalyticsController extends Controller
{
    public function __construct(protected AnalyticsService $analytics) {}

    /** GET /api/admin/analytics */
    public function dashboard()
    {
        return response()->json($this->analytics->dashboard());
    }

    /** GET /api/admin/analytics/metrics — Prometheus text format */
    public function metrics()
    {
        return response($this->analytics->prometheusMetrics(), 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }
}
