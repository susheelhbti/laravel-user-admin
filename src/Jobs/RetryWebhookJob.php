<?php
namespace Susheelhbti\LaravelUserAdmin\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Susheelhbti\LaravelUserAdmin\Models\WebhookEndpoint;
use Susheelhbti\LaravelUserAdmin\Services\WebhookService;

class RetryWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public function __construct(public int $endpointId, public string $event, public array $payload, public int $attempt) {}
    public function handle(WebhookService $service): void
    {
        $endpoint = WebhookEndpoint::find($this->endpointId);
        if (!$endpoint || !$endpoint->active) return;
        $service->deliver($endpoint, $this->event, $this->payload, $this->attempt);
    }
}
