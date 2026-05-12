<?php
namespace Susheelhbti\LaravelUserAdmin\Http\Controllers\Auth;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Susheelhbti\LaravelUserAdmin\Models\ApiKey;
use Susheelhbti\LaravelUserAdmin\Services\ApiKeyService;

class ApiKeyController extends Controller
{
    public function __construct(protected ApiKeyService $apiKeyService) {}

    public function index(Request $request)
    {
        return response()->json(['api_keys' => $this->apiKeyService->listForUser($request->user())]);
    }

    public function store(Request $request)
    {
        $request->validate(['name' => 'required|string|max:100', 'scopes' => 'nullable|array', 'expires_at' => 'nullable|date|after:now']);
        $result = $this->apiKeyService->create($request->user(), $request->name, $request->scopes ?? [], $request->expires_at ? new \DateTime($request->expires_at) : null);
        return response()->json(['message' => 'API key created. Save the raw key — it will not be shown again.', 'api_key' => $result['api_key'], 'raw_key' => $result['raw_key']], 201);
    }

    public function rotate(ApiKey $apiKey)
    {
        $result = $this->apiKeyService->rotate($apiKey);
        return response()->json(['message' => 'API key rotated.', 'api_key' => $result['api_key'], 'raw_key' => $result['raw_key']]);
    }

    public function destroy(ApiKey $apiKey)
    {
        $this->apiKeyService->revoke($apiKey);
        return response()->json(['message' => 'API key revoked.']);
    }
}
