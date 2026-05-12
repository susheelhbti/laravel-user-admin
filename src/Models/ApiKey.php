<?php
namespace Susheelhbti\LaravelUserAdmin\Models;
use Illuminate\Database\Eloquent\Model;
class ApiKey extends Model {
    protected $table = 'user_admin_api_keys';
    protected $fillable = ['user_id', 'name', 'key', 'key_prefix', 'scopes', 'expires_at', 'last_used_at', 'use_count', 'revoked_at'];
    protected $casts = ['scopes' => 'array', 'expires_at' => 'datetime', 'last_used_at' => 'datetime', 'revoked_at' => 'datetime'];
    public function user() { return $this->belongsTo(config('user_admin.user_model', \App\Models\User::class)); }
}
