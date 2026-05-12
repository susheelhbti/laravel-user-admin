<?php
namespace Susheelhbti\LaravelUserAdmin\Models;
use Illuminate\Database\Eloquent\Model;
class WebhookDelivery extends Model {
    protected $table = 'user_admin_webhook_deliveries';
    protected $fillable = ['endpoint_id', 'event', 'payload', 'attempt', 'status', 'response_status', 'response_body', 'next_attempt_at'];
    protected $casts = ['payload' => 'array', 'next_attempt_at' => 'datetime'];
    public function endpoint() { return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id'); }
}
