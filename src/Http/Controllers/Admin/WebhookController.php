<?php
namespace Susheelhbti\LaravelUserAdmin\Http\Controllers\Admin;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Susheelhbti\LaravelUserAdmin\Models\WebhookEndpoint;
use Susheelhbti\LaravelUserAdmin\Models\WebhookDelivery;
use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;

class WebhookController extends Controller
{
    public function index()
    {
        return response()->json(WebhookEndpoint::withCount('deliveries')->paginate(20));
    }

    public function store(Request $request)
    {
        $request->validate([
            'url'         => 'required|url|max:500',
            'events'      => 'required|array',
            'events.*'    => 'string',
            'description' => 'nullable|string|max:255',
        ]);

        $endpoint = WebhookEndpoint::create([
            'url'         => $request->url,
            'secret'      => Str::random(32),
            'events'      => $request->events,
            'active'      => true,
            'description' => $request->description,
        ]);

        return response()->json([
            'message'  => 'Webhook endpoint created.',
            'endpoint' => $endpoint,
            'secret'   => $endpoint->secret,   // shown only once
        ], 201);
    }

    public function update(Request $request, WebhookEndpoint $endpoint)
    {
        $request->validate(['url' => 'sometimes|url', 'events' => 'sometimes|array', 'active' => 'sometimes|boolean']);
        $endpoint->update($request->only(['url', 'events', 'active', 'description']));
        return response()->json(['message' => 'Webhook updated.', 'endpoint' => $endpoint]);
    }

    public function destroy(WebhookEndpoint $endpoint)
    {
        $endpoint->delete();
        return response()->json(['message' => 'Webhook deleted.']);
    }

    public function rotateSecret(WebhookEndpoint $endpoint)
    {
        $secret = Str::random(32);
        $endpoint->update(['secret' => $secret]);
        return response()->json(['message' => 'Secret rotated.', 'secret' => $secret]);
    }

    public function deliveries(WebhookEndpoint $endpoint)
    {
        return response()->json($endpoint->deliveries()->latest()->paginate(50));
    }

    public function availableEvents()
    {
        return response()->json(['events' => array_values(UserAdminEvents::all())]);
    }

    public function testDeliver(Request $request, WebhookEndpoint $endpoint)
    {
        app(\Susheelhbti\LaravelUserAdmin\Services\WebhookService::class)->deliver(
            $endpoint, 'user_admin.test', ['message' => 'This is a test delivery.']
        );
        return response()->json(['message' => 'Test delivery dispatched.']);
    }
}
