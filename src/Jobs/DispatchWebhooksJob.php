<?php
namespace Susheelhbti\LaravelUserAdmin\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Susheelhbti\LaravelUserAdmin\Services\WebhookService;

class DispatchWebhooksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public string $event, public array $payload) {}
    public function handle(WebhookService $service): void { $service->dispatch($this->event, $this->payload); }
}
