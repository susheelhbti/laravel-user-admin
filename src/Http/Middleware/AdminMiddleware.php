<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || !method_exists($user, 'isOtpAdmin') || !$user->isOtpAdmin()) {
            return response()->json([
                'message' => 'Unauthorized. Admin access required.',
            ], 403);
        }

        return $next($request);
    }
}
