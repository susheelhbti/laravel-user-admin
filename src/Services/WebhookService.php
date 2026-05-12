<?php

namespace Susheelhbti\LaravelUserAdmin\Services;

use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;
use Susheelhbti\LaravelUserAdmin\Models\WebhookEndpoint;
use Susheelhbti\LaravelUserAdmin\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Dispatch all matching webhooks for the given event.
     * Each delivery is queued via Laravel's job system.
     */
    public function dispatch(string $event, array $payload): void
    {
        $endpoints = WebhookEndpoint::active()->forEvent($event)->get();

        foreach ($endpoints as $endpoint) {
            $this->deliver($endpoint, $event, $payload);
        }
    }

    /**
     * Attempt delivery to a single endpoint.
     * On failure, schedules a retry with exponential backoff.
     */
    public function deliver(WebhookEndpoint $endpoint, string $event, array $payload, int $attempt = 1): void
    {
        $body      = json_encode(array_merge($payload, ['event' => $event, 'delivered_at' => now()->toISOString()]));
        $signature = $this->sign($body, $endpoint->secret);

        $delivery = WebhookDelivery::create([
            'endpoint_id' => $endpoint->id,
            'event'       => $event,
            'payload'     => $payload,
            'attempt'     => $attempt,
            'status'      => 'pending',
        ]);

        try {
            $resp = Http::timeout(10)
                ->withHeaders([
                    'Content-Type'              => 'application/json',
                    'X-UserAdmin-Event'         => $event,
                    'X-UserAdmin-Signature'     => $signature,
                    'X-UserAdmin-Delivery'      => $delivery->id,
                    'X-UserAdmin-Attempt'       => $attempt,
                ])
                ->withBody($body, 'application/json')
                ->post($endpoint->url);

            $delivery->update([
                'status'          => $resp->successful() ? 'delivered' : 'failed',
                'response_status' => $resp->status(),
                'response_body'   => substr($resp->body(), 0, 1000),
            ]);

            if ($resp->successful()) {
                $endpoint->increment('success_count');
                UserAdminEvents::fire(UserAdminEvents::WEBHOOK_DELIVERED, [
                    'endpoint_id' => $endpoint->id,
                    'event'       => $event,
                    'delivery_id' => $delivery->id,
                ]);
                return;
            }

            $this->scheduleRetry($endpoint, $event, $payload, $attempt, $delivery);

        } catch (\Throwable $e) {
            $delivery->update(['status' => 'failed', 'response_body' => $e->getMessage()]);
            $endpoint->increment('fail_count');
            $this->scheduleRetry($endpoint, $event, $payload, $attempt, $delivery);

            UserAdminEvents::fire(UserAdminEvents::WEBHOOK_FAILED, [
                'endpoint_id' => $endpoint->id,
                'event'       => $event,
                'delivery_id' => $delivery->id,
                'error'       => $e->getMessage(),
            ]);
        }
    }

    /** HMAC-SHA256 signature for payload verification. */
    public function sign(string $payload, string $secret): string
    {
        return 'sha256=' . hash_hmac('sha256', $payload, $secret);
    }

    /** Verify incoming webhook from third-party (inbound webhooks). */
    public function verify(string $payload, string $signature, string $secret): bool
    {
        return hash_equals($this->sign($payload, $secret), $signature);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function scheduleRetry(WebhookEndpoint $endpoint, string $event, array $payload, int $attempt, WebhookDelivery $delivery): void
    {
        $maxAttempts = config('user_admin.webhooks.max_attempts', 5);

        if ($attempt >= $maxAttempts) {
            $delivery->update(['status' => 'exhausted']);
            $endpoint->update(['active' => false]);
            Log::warning("user-admin: webhook endpoint #{$endpoint->id} deactivated after {$maxAttempts} failed attempts.");
            return;
        }

        // Exponential backoff: 1m, 5m, 30m, 2h, 10h
        $delayMinutes = [1, 5, 30, 120, 600][$attempt - 1] ?? 600;

        \Susheelhbti\LaravelUserAdmin\Jobs\RetryWebhookJob::dispatch(
            $endpoint->id, $event, $payload, $attempt + 1
        )->delay(now()->addMinutes($delayMinutes));

        $delivery->update(['status' => 'retrying', 'next_attempt_at' => now()->addMinutes($delayMinutes)]);

        UserAdminEvents::fire(UserAdminEvents::WEBHOOK_RETRIED, [
            'endpoint_id'     => $endpoint->id,
            'event'           => $event,
            'attempt'         => $attempt + 1,
            'delay_minutes'   => $delayMinutes,
        ]);
    }
}
