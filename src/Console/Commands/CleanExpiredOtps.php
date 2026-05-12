<?php

namespace Susheelhbti\LaravelUserAdmin\Console\Commands;

use Illuminate\Console\Command;
use Susheelhbti\LaravelUserAdmin\Models\Otp;

class CleanExpiredOtps extends Command
{
    protected $signature   = 'user-admin:clean-otps';
    protected $description = 'Delete expired and verified OTP records';

    public function handle(): int
    {
        $deleted = Otp::where(function ($q) {
            $q->where('expires_at', '<', now())
              ->orWhereNotNull('verified_at');
        })->delete();

        $this->info("Cleaned {$deleted} OTP records.");

        return self::SUCCESS;
    }
}
