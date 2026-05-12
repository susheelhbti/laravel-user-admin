<?php

namespace Susheelhbti\LaravelUserAdmin\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Susheelhbti\LaravelUserAdmin\LaravelUserAdminServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaravelUserAdminServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('auth.defaults.guard', 'sanctum');
        $app['config']->set('auth.guards.sanctum', [
            'driver'   => 'sanctum',
            'provider' => 'users',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
