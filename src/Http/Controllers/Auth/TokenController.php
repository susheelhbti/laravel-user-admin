<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Susheelhbti\LaravelUserAdmin\Services\RefreshTokenService;

class TokenController extends Controller
{
    public function __construct(protected RefreshTokenService $refreshService) {}

    /** POST /api/auth/token/refresh */
    public function refresh(Request $request)
    {
        $request->validate(['refresh_token' => 'required|string']);

        $user = $request->user();   // still authenticated via the old access token

        try {
            $tokens = $this->refreshService->rotate($request->refresh_token, $user);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        }

        return response()->json([
            'message'       => 'Token refreshed.',
            'access_token'  => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
        ]);
    }
}
