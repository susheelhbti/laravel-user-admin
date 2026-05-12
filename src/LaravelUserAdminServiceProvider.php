<?php

namespace Susheelhbti\LaravelUserAdmin;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Susheelhbti\LaravelUserAdmin\Console\Commands\{
    CleanExpiredOtps, PurgeDeletedAccounts, ArchiveInactiveUsers, ExpireAccounts
};
use Susheelhbti\LaravelUserAdmin\Http\Middleware\{AdminMiddleware, RoleMiddleware, ApiKeyMiddleware};
use Susheelhbti\LaravelUserAdmin\Services\{
    OtpService, TwoFactorService, RefreshTokenService, DeviceService,
    AccountDeletionService, ImportService, GeoService, TeamService,
    ApiKeyService, WebhookService, GdprService, UserLifecycleService,
    SecurityQuestionService, AnalyticsService, ConditionalAuthService
};

class LaravelUserAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/user_admin.php', 'user_admin');

        // Register all services as singletons
        foreach ([
            TwoFactorService::class, RefreshTokenService::class, DeviceService::class,
            AccountDeletionService::class, ImportService::class, GeoService::class,
            TeamService::class, ApiKeyService::class, WebhookService::class,
            GdprService::class, UserLifecycleService::class, SecurityQuestionService::class,
            AnalyticsService::class, ConditionalAuthService::class,
        ] as $service) {
            $this->app->singleton($service);
        }

        // OtpService has dependencies — wire them explicitly
        $this->app->singleton(OtpService::class, fn ($app) => new OtpService(
            $app->make(TwoFactorService::class),
            $app->make(RefreshTokenService::class),
            $app->make(DeviceService::class),
            $app->make(GeoService::class),
            $app->make(ConditionalAuthService::class),
        ));
    }

    public function boot(): void
    {
        $this->registerMigrations();
        $this->registerRoutes();
        $this->registerMiddleware();
        $this->registerPublishables();
        $this->registerViews();
        $this->registerCommands();
        $this->registerWebhookListener();
    }

    protected function registerMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function registerRoutes(): void
    {
        if (config('user_admin.register_routes', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('user-admin.admin',  AdminMiddleware::class);
        $router->aliasMiddleware('user-admin.role',   RoleMiddleware::class);
        $router->aliasMiddleware('user-admin.apikey', ApiKeyMiddleware::class);
    }

    protected function registerPublishables(): void
    {
        $this->publishes([__DIR__ . '/../config/user_admin.php'   => config_path('user_admin.php')],        'laravel-user-admin-config');
        $this->publishes([__DIR__ . '/../database/migrations'     => database_path('migrations')],           'laravel-user-admin-migrations');
        $this->publishes([__DIR__ . '/../database/seeders'        => database_path('seeders')],              'laravel-user-admin-seeders');
        $this->publishes([__DIR__ . '/../resources/views'         => resource_path('views/vendor/laravel-user-admin')], 'laravel-user-admin-views');
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'laravel-user-admin');
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanExpiredOtps::class,
                PurgeDeletedAccounts::class,
                ArchiveInactiveUsers::class,
                ExpireAccounts::class,
            ]);
        }
    }

    /**
     * Wire every fired event → WebhookService::dispatch()
     * so webhook delivery is automatic for all 60+ events.
     */
    protected function registerWebhookListener(): void
    {
        if (!config('user_admin.webhooks.enabled', true)) return;

        $constants = \Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents::all();

        foreach ($constants as $event) {
            \Illuminate\Support\Facades\Event::listen($event, function (string $eventName, array $data) {
                \Susheelhbti\LaravelUserAdmin\Jobs\DispatchWebhooksJob::dispatch($eventName, $data);
            });
        }
    }
}
