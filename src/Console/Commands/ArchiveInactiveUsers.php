<?php
namespace Susheelhbti\LaravelUserAdmin\Console\Commands;
use Illuminate\Console\Command;
use Susheelhbti\LaravelUserAdmin\Services\UserLifecycleService;
class ArchiveInactiveUsers extends Command
{
    protected $signature   = 'user-admin:archive-inactive {--days=180 : Inactive days threshold}';
    protected $description = 'Archive users who have not logged in for the given number of days.';
    public function handle(UserLifecycleService $service): int
    {
        $count = $service->archiveInactive((int) $this->option('days'));
        $this->info("Archived {$count} inactive user(s).");
        return self::SUCCESS;
    }
}
