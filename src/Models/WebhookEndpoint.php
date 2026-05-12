<?php
namespace Susheelhbti\LaravelUserAdmin\Models;
use Illuminate\Database\Eloquent\Model;
class WebhookEndpoint extends Model {
    protected $table = 'user_admin_webhook_endpoints';
    protected $fillable = ['url', 'secret', 'events', 'active', 'description', 'success_count', 'fail_count'];
    protected $casts = ['events' => 'array', 'active' => 'boolean'];
    public function deliveries() { return $this->hasMany(WebhookDelivery::class, 'endpoint_id'); }
    public function scopeActive($q) { return $q->where('active', true); }
    public function scopeForEvent($q, $event) { return $q->whereJsonContains('events', $event)->orWhereJsonContains('events', '*'); }
}
