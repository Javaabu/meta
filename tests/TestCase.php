<?php

namespace Javaabu\Meta\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Javaabu\Forms\FormsServiceProvider;
use Javaabu\Manifest\ManifestServiceProvider;
use Javaabu\Meta\MetaServiceProvider;
use Javaabu\Stencil\StencilServiceProvider;
use Javaabu\Translatable\TranslatableServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            ManifestServiceProvider::class,
            FormsServiceProvider::class,
            StencilServiceProvider::class,
        ];

        // Optional: the CI leg without javaabu/translatable removes it.
        if (class_exists(TranslatableServiceProvider::class)) {
            $providers[] = TranslatableServiceProvider::class;
        }

        return [...$providers, MetaServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:yWa/ByhLC/GUvfToOuaPD7zDwB64qkc/QkaQOrT5IpE=');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', static::databaseConnection());
    }

    /**
     * SQLite in memory, or MySQL when DB_CONNECTION=mysql (as in CI's MySQL job).
     *
     * @return array<string, mixed>
     */
    public static function databaseConnection(): array
    {
        if (env('DB_CONNECTION') === 'mysql') {
            return [
                'driver' => 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => env('DB_DATABASE', 'meta_test'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => 'InnoDB',
            ];
        }

        return [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];
    }

    protected function usingMysql(): bool
    {
        return $this->app['db']->connection()->getDriverName() === 'mysql';
    }
}
