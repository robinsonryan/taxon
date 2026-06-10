<?php

declare(strict_types=1);

use RobinsonRyan\Taxon\TaxonServiceProvider;

describe('Phase 1 Scaffolding', function (): void {
    it('can load the service provider', function (): void {
        expect(class_exists(TaxonServiceProvider::class))->toBeTrue();
    });

    it('can access taxon config', function (): void {
        expect(config('taxon.tables.tags'))->toBe('tags');
        expect(config('taxon.tables.taggables'))->toBe('taggables');
    });

    it('has default id_type as incrementing', function (): void {
        expect(config('taxon.id_type'))->toBe('incrementing');
    });
});
