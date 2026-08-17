<?php

declare(strict_types=1);

use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Models\Taggable;
use RobinsonRyan\Taxon\Support\SchemaSupport;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModelWithAttributes;

/*
|--------------------------------------------------------------------------
| The migration helper, and the claim it reasons from
|--------------------------------------------------------------------------
|
| Two things are pinned here. The capability probe has to answer for the
| connection in hand rather than for whichever one asked first, because a
| process can migrate several in sequence. And the reason the column default is
| load-bearing has to be the true one: `attach()` writes the pivot through a
| Taggable *model*, not through the query builder, so "no model is involved"
| was never why nothing generates the key — "no model generates one" is.
|
*/

afterEach(function (): void {
    config()->set('database.default', 'testing');
    SchemaSupport::flushCapabilityCache();
});

describe('the uuidv7 capability probe', function (): void {
    it('reports the function on the PostgreSQL 18 connection the suite runs on', function (): void {
        SchemaSupport::flushCapabilityCache();

        expect(SchemaSupport::supportsUuidV7())->toBeTrue();
    });

    it('answers per connection rather than once per process', function (): void {
        SchemaSupport::flushCapabilityCache();

        // Ask PostgreSQL first, so a process-wide cache would be holding `true`
        // by the time a connection that cannot generate a uuid7 asks.
        expect(SchemaSupport::supportsUuidV7())->toBeTrue();

        config()->set('database.connections.sqlite_probe', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('database.default', 'sqlite_probe');

        expect(SchemaSupport::supportsUuidV7())->toBeFalse();
    });

    it('still answers PostgreSQL correctly after a non-PostgreSQL connection has asked', function (): void {
        SchemaSupport::flushCapabilityCache();

        config()->set('database.connections.sqlite_probe', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('database.default', 'sqlite_probe');

        expect(SchemaSupport::supportsUuidV7())->toBeFalse();

        config()->set('database.default', 'testing');

        expect(SchemaSupport::supportsUuidV7())->toBeTrue();
    });
});

describe('the pivot write path', function (): void {
    it('saves the pivot through the Taggable model, so model events do fire', function (): void {
        Tag::createCategory('Status', singleSelect: true)->addChildren(['pending']);

        $model = TestModelWithAttributes::create(['name' => 'Test']);

        $created = 0;
        Taggable::creating(function () use (&$created): void {
            $created++;
        });

        $model->setTag('status', 'pending');

        expect($created)->toBe(1);
    });
});
