<?php

namespace Susheelhbti\LaravelUserAdmin;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Susheelhbti\LaravelUserAdmin\Console\Commands\CleanExpiredOtps;
use Susheelhbti\LaravelUserAdmin\Http\Middleware\AdminMiddleware;
use Susheelhbti\LaravelUserAdmin\Http\Middleware\RoleMiddleware;
use Susheelhbti\LaravelUserAdmin\Services\OtpService;

class LaravelUserAdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/user_admin.php', 'user_admin');

        $this->app->singleton(OtpService::class, function ($app) {
            return new OtpService();
        });
    }

    public function boot(): void
    {
        $this->registerMigrations();
        $this->registerRoutes();
        $this->registerMiddleware();
        $this->registerPublishables();
        $this->registerViews();
        $this->registerCommands();
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
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('user-admin.admin', AdminMiddleware::class);
        $router->aliasMiddleware('user-admin.role', RoleMiddleware::class);
    }

    protected function registerPublishables(): void
    {
        $this->publishes([
            __DIR__ . '/../config/user_admin.php' => config_path('user_admin.php'),
        ], 'laravel-user-admin-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'laravel-user-admin-migrations');

        $this->publishes([
            __DIR__ . '/../database/seeders' => database_path('seeders'),
        ], 'laravel-user-admin-seeders');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/laravel-user-admin'),
        ], 'laravel-user-admin-views');
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
            ]);
        }
    }
}
