<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Tests;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use RobinsonRyan\Taxon\TaxonServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            TaxonServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // The suite runs against a real PostgreSQL database (the DDEV `db`
        // service), not SQLite. SQLite compiles `uuid`, `bigint` and `varchar`
        // down to the same loose affinity, so it cannot see a column-type
        // mismatch — which is exactly how a `uuid`-typed `taggable_id` once
        // shipped as the default schema.
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'pgsql',
            'host' => env('TAXON_TEST_DB_HOST', 'db'),
            'port' => (int) env('TAXON_TEST_DB_PORT', 5432),
            'database' => env('TAXON_TEST_DB_DATABASE', 'testing'),
            'username' => env('TAXON_TEST_DB_USERNAME', 'db'),
            'password' => env('TAXON_TEST_DB_PASSWORD', 'db'),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ]);

        // Default to incrementing IDs
        $app['config']->set('taxon.id_type', 'incrementing');

        // Register the package migrations (the service provider only publishes
        // them) and the fixture consumer tables with the migrator, so the
        // one-time RefreshDatabase migration run picks up both. Registering the
        // path — rather than calling loadMigrationsFrom() — keeps Testbench from
        // tearing the schema down and rebuilding it per test.
        $app->afterResolving('migrator', static function (Migrator $migrator): void {
            $migrator->path(__DIR__ . '/../database/migrations');
            $migrator->path(__DIR__ . '/Fixtures/database/migrations');
        });
    }
}
