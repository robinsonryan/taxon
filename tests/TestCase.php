<?php

namespace RobinsonRyan\Taxon\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RobinsonRyan\Taxon\TaxonServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            TaxonServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Default to incrementing IDs
        $app['config']->set('taxon.id_type', 'incrementing');
    }

    protected function setUpDatabase(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->createTestTables();
    }

    protected function createTestTables(): void
    {
        $useUuid = config('taxon.id_type') === 'uuid7';

        Schema::create('test_models', function (Blueprint $table) use ($useUuid) {
            if ($useUuid) {
                $table->uuid('id')->primary();
            } else {
                $table->id();
            }
            $table->string('name');
            $table->string('account_id')->nullable();
            $table->timestamps();
        });

        Schema::create('test_users', function (Blueprint $table) use ($useUuid) {
            if ($useUuid) {
                $table->uuid('id')->primary();
            } else {
                $table->id();
            }
            $table->string('name');
            $table->string('email');
            $table->string('account_id')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });

        Schema::create('test_organizations', function (Blueprint $table) use ($useUuid) {
            if ($useUuid) {
                $table->uuid('id')->primary();
            } else {
                $table->id();
            }
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Helper to run tests with UUID7 configuration
     */
    protected function useUuid7(): void
    {
        config()->set('taxon.id_type', 'uuid7');

        // Recreate tables with UUID columns
        Schema::dropIfExists('test_organizations');
        Schema::dropIfExists('test_users');
        Schema::dropIfExists('test_models');
        Schema::dropIfExists(config('taxon.tables.taggables'));
        Schema::dropIfExists(config('taxon.tables.tags'));

        $this->setUpDatabase();
    }
}
