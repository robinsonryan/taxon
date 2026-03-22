<?php

use RobinsonRyan\Taxon\TaxonServiceProvider;

describe('Phase 1 Scaffolding', function () {
    it('can load the service provider', function () {
        expect(class_exists(TaxonServiceProvider::class))->toBeTrue();
    });

    it('can access taxon config', function () {
        expect(config('taxon.tables.tags'))->toBe('tags');
        expect(config('taxon.tables.taggables'))->toBe('taggables');
    });

    it('has default id_type as incrementing', function () {
        expect(config('taxon.id_type'))->toBe('incrementing');
    });
});
