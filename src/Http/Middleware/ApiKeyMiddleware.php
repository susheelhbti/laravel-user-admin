<?php

namespace Susheelhbti\LaravelUserAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Susheelhbti\LaravelUserAdmin\Services\ApiKeyService;

/**
 * Authenticates requests using a scoped API key.
 *
 * The key is read from:
 *   1. Authorization: Bearer <key>   (if it starts with 'uak_')
 *   2. X-Api-Key: <key> header
 *   3. ?api_key=<key> query parameter
 *
 * Usage in routes:
 *   Route::get('/data', handler)->middleware('user-admin.apikey:read:data');
 *
 * Usage in ServiceProvider / kernel:
 *   $router->aliasMiddleware('user-admin.apikey', ApiKeyMiddleware::class);
 */
class ApiKeyMiddleware
{
    public function __construct(protected ApiKeyService $apiKeyService) {}

    public function handle(Request $request, Closure $next, string $scope = ''): mixed
    {
        $rawKey = $this->extractKey($request);

        if (!$rawKey) {
            return response()->json(['message' => 'API key required.'], 401);
        }

        $apiKey = $this->apiKeyService->validate($rawKey, $scope);

        if (!$apiKey) {
            return response()->json(['message' => 'Invalid, expired, or insufficient-scope API key.'], 401);
        }

        // Load the owner and set them as the authenticated user for this request
        $userModel = config('user_admin.user_model', \App\Models\User::class);
        $user      = $userModel::find($apiKey->user_id);

        if (!$user || $user->isSuspended()) {
            return response()->json(['message' => 'Account unavailable.'], 403);
        }

        // Bind user into the request — works with auth()->user() and $request->user()
        auth()->setUser($user);
        $request->merge(['_api_key_id' => $apiKey->id]);

        return $next($request);
    }

    private function extractKey(Request $request): ?string
    {
        // Bearer token starting with 'uak_' → it's an API key, not a Sanctum token
        $bearer = $request->bearerToken();
        if ($bearer && str_starts_with($bearer, 'uak_')) {
            return $bearer;
        }

        // Explicit header
        $header = $request->header('X-Api-Key');
        if ($header) {
            return $header;
        }

        // Query param (last resort — avoid in production)
        return $request->query('api_key');
    }
}
