<?php

namespace Susheelhbti\LaravelUserAdmin\Console\Commands;

use Illuminate\Console\Command;
use Susheelhbti\LaravelUserAdmin\Services\AccountDeletionService;

class PurgeDeletedAccounts extends Command
{
    protected $signature   = 'user-admin:purge-accounts';
    protected $description = 'Permanently delete accounts whose grace period has elapsed.';

    public function handle(AccountDeletionService $service): int
    {
        $count = $service->purgeExpired();
        $this->info("Purged {$count} account(s).");
        return self::SUCCESS;
    }
}
