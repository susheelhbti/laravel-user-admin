<?php
namespace Susheelhbti\LaravelUserAdmin\Console\Commands;
use Illuminate\Console\Command;
use Susheelhbti\LaravelUserAdmin\Services\UserLifecycleService;
class ExpireAccounts extends Command
{
    protected $signature   = 'user-admin:expire-accounts';
    protected $description = 'Expire temporary accounts whose account_expires_at has passed.';
    public function handle(UserLifecycleService $service): int
    {
        $count = $service->expireAccounts();
        $this->info("Expired {$count} account(s).");
        return self::SUCCESS;
    }
}
