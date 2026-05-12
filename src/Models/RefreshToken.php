<?php

namespace Susheelhbti\LaravelUserAdmin\Models;

use Illuminate\Database\Eloquent\Model;

class RefreshToken extends Model
{
    protected $table = 'user_admin_refresh_tokens';

    protected $fillable = [
        'user_id', 'access_token_id', 'token', 'family', 'expires_at', 'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function accessToken()
    {
        return $this->belongsTo(\Laravel\Sanctum\PersonalAccessToken::class, 'access_token_id');
    }
}
