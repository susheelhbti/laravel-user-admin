<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $user = $request->user();

        if (!$user || !method_exists($user, 'hasOtpRole') || !$user->hasOtpRole($role)) {
            return response()->json([
                'message' => "Unauthorized. Required role: {$role}",
            ], 403);
        }

        return $next($request);
    }
}
