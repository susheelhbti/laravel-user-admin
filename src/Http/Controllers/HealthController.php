<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /** GET /api/health */
    public function __invoke()
    {
        $dbOk = false;
        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (\Throwable) {}

        $status = $dbOk ? 'ok' : 'degraded';

        return response()->json([
            'status'  => $status,
            'package' => 'susheelhbti/laravel-user-admin',
            'version' => '2.0.0',
            'checks'  => [
                'database' => $dbOk ? 'ok' : 'error',
            ],
            'timestamp' => now()->toISOString(),
        ], $dbOk ? 200 : 503);
    }
}
