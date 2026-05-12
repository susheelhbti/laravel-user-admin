<?php

namespace Susheelhbti\LaravelUserAdmin\Models;

use Illuminate\Database\Eloquent\Model;

class TrustedDevice extends Model
{
    protected $table = 'user_admin_trusted_devices';

    protected $fillable = [
        'user_id', 'token', 'device_name', 'fingerprint', 'ip_address', 'last_used_at', 'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];
}
