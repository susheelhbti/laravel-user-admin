<?php

namespace Susheelhbti\LaravelUserAdmin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;

    protected $table = 'otpguard_otps';

    protected $fillable = [
        'email', 'code', 'session_id', 'expires_at', 'attempts', 'verified_at',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function isValid(): bool
    {
        return !$this->verified_at
            && $this->expires_at->isFuture()
            && $this->attempts < config('user_admin.otp.max_attempts', 3);
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts');

        if ($this->attempts >= config('user_admin.otp.max_attempts', 3)) {
            $this->update(['expires_at' => now()]);
        }
    }

    public function markAsVerified(): void
    {
        $this->update(['verified_at' => now()]);
    }
}
