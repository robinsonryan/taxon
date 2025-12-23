<?php

namespace RobinsonRyan\Taxon;

use Illuminate\Support\ServiceProvider;

class TaxonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/taxon.php',
            'taxon'
        );
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->publishMigrations();
    }

    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/taxon.php' => config_path('taxon.php'),
        ], 'taxon-config');
    }

    protected function publishMigrations(): void
    {
        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'taxon-migrations');
    }
}
