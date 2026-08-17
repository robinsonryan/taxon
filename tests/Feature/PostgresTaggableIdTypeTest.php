<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RobinsonRyan\Taxon\Support\SchemaSupport;

/*
|--------------------------------------------------------------------------
| Why this file talks to a real PostgreSQL server
|--------------------------------------------------------------------------
|
| The rest of the suite also runs on PostgreSQL now, but it runs under the SHIPPED
| default configuration, against a schema RefreshDatabase migrates exactly once.
| This file is the one that varies `id_type` / `taggable_id_type`, which means
| dropping and recreating `tags` and `taggables` per case — destructive to that
| shared schema. So it works on its own connection, in the `db` database rather
| than `testing`, and reads `information_schema` directly: no Laravel type mapping
| in between. It skips — loudly — where no PostgreSQL server is reachable.
|
| Column types are what is on trial here, and they are unfalsifiable on SQLite,
| where `uuid`, `bigint` and `varchar` all collapse to the same loose affinity.
| That blindness is precisely how a `uuid`-typed `taggable_id` shipped as the
| default schema.
|
*/

const PG_CONNECTION = 'pgsql_schema_probe';

/** @return array<string, mixed> */
function pgConnectionConfig(): array
{
    return [
        'driver' => 'pgsql',
        'host' => env('PGHOST', 'db'),
        'port' => env('PGPORT', '5432'),
        'database' => env('PGDATABASE', 'db'),
        'username' => env('PGUSER', 'db'),
        'password' => env('PGPASSWORD', 'db'),
        'charset' => 'utf8',
        'prefix' => '',
        'prefix_indexes' => true,
        'search_path' => 'public',
        'sslmode' => 'prefer',
    ];
}

function dropTaxonTables(): void
{
    $schema = Schema::connection(PG_CONNECTION);

    $schema->dropIfExists('taggables');
    $schema->dropIfExists('tags');
}

/**
 * Run the package's published migrations, in order, against PostgreSQL under the
 * given config. All of them, not just the first — a consumer gets the whole set,
 * and the scope-column migration ends in a raw CREATE UNIQUE INDEX that has never
 * been exercised on anything but SQLite.
 *
 * @param  array<string, mixed>  $taxonConfig
 */
function migrateOnPostgres(array $taxonConfig): void
{
    config($taxonConfig);
    config()->set('database.default', PG_CONNECTION);

    $files = glob(__DIR__ . '/../../database/migrations/*.php');
    $files = $files === false ? [] : $files;
    sort($files);

    foreach ($files as $file) {
        $migration = require $file;

        if ($migration instanceof Migration) {
            $migration->up();
        }
    }
}

/**
 * Run one of the package's published migrations by filename fragment, against
 * PostgreSQL under the given config. The upgrade migrations have to be runnable
 * on their own, on a schema an earlier release already built.
 *
 * @param  array<string, mixed>  $taxonConfig
 */
function migrateOneOnPostgres(string $fragment, array $taxonConfig): void
{
    config($taxonConfig);
    config()->set('database.default', PG_CONNECTION);

    $files = glob(__DIR__ . '/../../database/migrations/*' . $fragment . '*.php');
    $files = $files === false ? [] : $files;

    expect($files)->toHaveCount(1);

    $migration = require $files[0];

    if ($migration instanceof Migration) {
        $migration->up();
    }
}

/** The PostgreSQL type of a column, straight from the catalog — no Laravel mapping in between. */
function pgColumnType(string $table, string $column): string
{
    $type = DB::connection(PG_CONNECTION)
        ->table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('table_name', $table)
        ->where('column_name', $column)
        ->value('data_type');

    return is_string($type) ? $type : 'missing';
}

/** The DEFAULT expression on a column, straight from the catalog. Null when there is none. */
function pgColumnDefault(string $table, string $column): ?string
{
    $default = DB::connection(PG_CONNECTION)
        ->table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('table_name', $table)
        ->where('column_name', $column)
        ->value('column_default');

    return is_string($default) ? $default : null;
}

/** Put a uuid7 install back into the pre-0.5.0 state: uuid keys, no database default. */
function stripUuidDefaults(): void
{
    foreach (['tags', 'taggables'] as $table) {
        DB::connection(PG_CONNECTION)->statement("alter table {$table} alter column id drop default");
    }
}

beforeEach(function (): void {
    config()->set('database.connections.' . PG_CONNECTION, pgConnectionConfig());

    try {
        DB::connection(PG_CONNECTION)->getPdo();
    } catch (Throwable $e) {
        $this->markTestSkipped(
            'No PostgreSQL server reachable (' . $e->getMessage() . '). Run this suite inside DDEV.'
        );
    }

    // The capability probe caches per process, and this file migrates on a
    // connection the rest of the suite never touches.
    SchemaSupport::flushCapabilityCache();

    dropTaxonTables();
});

afterEach(function (): void {
    dropTaxonTables();

    // migrateOnPostgres() repoints the default connection; hand it back, or the
    // framework's own teardown looks for a migrations table on PostgreSQL.
    config()->set('database.default', 'testing');
});

describe('default configuration — incrementing tag ids', function (): void {
    it('gives taggable_id a big integer type, so an integer-keyed host model fits', function (): void {
        migrateOnPostgres(['taxon.id_type' => 'incrementing']);

        expect(pgColumnType('taggables', 'taggable_id'))->toBe('bigint');
    });

    it('accepts an integer taggable_id on insert', function (): void {
        migrateOnPostgres(['taxon.id_type' => 'incrementing']);

        $tagId = DB::connection(PG_CONNECTION)->table('tags')->insertGetId([
            'name' => 'Alpha',
            'slug' => 'alpha',
            'assignable' => true,
            'single_select' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection(PG_CONNECTION)->table('taggables')->insert([
            'tag_id' => $tagId,
            'taggable_type' => 'test_model',
            'taggable_id' => 42,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(
            DB::connection(PG_CONNECTION)->table('taggables')->where('taggable_id', 42)->count()
        )->toBe(1);
    });
});

describe('uuid7 configuration', function (): void {
    it('gives taggable_id a uuid type, so a uuid-keyed host model fits', function (): void {
        migrateOnPostgres(['taxon.id_type' => 'uuid7']);

        expect(pgColumnType('taggables', 'taggable_id'))->toBe('uuid');
    });

    it('accepts a uuid taggable_id on insert', function (): void {
        migrateOnPostgres(['taxon.id_type' => 'uuid7']);

        $tagId = Str::uuid7()->toString();
        $modelId = Str::uuid7()->toString();

        DB::connection(PG_CONNECTION)->table('tags')->insert([
            'id' => $tagId,
            'name' => 'Alpha',
            'slug' => 'alpha',
            'assignable' => true,
            'single_select' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection(PG_CONNECTION)->table('taggables')->insert([
            'id' => Str::uuid7()->toString(),
            'tag_id' => $tagId,
            'taggable_type' => 'test_model',
            'taggable_id' => $modelId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        expect(
            DB::connection(PG_CONNECTION)->table('taggables')->where('taggable_id', $modelId)->count()
        )->toBe(1);
    });
});

describe('uuid7 keys are generated by the database, not by PHP', function (): void {
    it('defaults tags.id to the database uuidv7() function', function (): void {
        migrateOnPostgres(['taxon.id_type' => 'uuid7']);

        expect(pgColumnDefault('tags', 'id'))->toContain('uuidv7()');
    });

    it('defaults taggables.id too, because attach() inserts through the query builder and never through a model', function (): void {
        migrateOnPostgres(['taxon.id_type' => 'uuid7']);

        expect(pgColumnDefault('taggables', 'id'))->toContain('uuidv7()');
    });

    it('accepts a pivot insert that supplies no id at all', function (): void {
        migrateOnPostgres(['taxon.id_type' => 'uuid7']);

        $tagId = Str::uuid7()->toString();

        DB::connection(PG_CONNECTION)->table('tags')->insert([
            'id' => $tagId,
            'name' => 'Alpha',
            'slug' => 'alpha',
            'assignable' => true,
            'single_select' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection(PG_CONNECTION)->table('taggables')->insert([
            'tag_id' => $tagId,
            'taggable_type' => 'test_model',
            'taggable_id' => Str::uuid7()->toString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::connection(PG_CONNECTION)->table('taggables')->value('id');

        expect($id)->toBeUuidV7();
    });

    it('leaves the bigint path on its sequence, with no uuid default anywhere near it', function (): void {
        migrateOnPostgres(['taxon.id_type' => 'incrementing']);

        expect(pgColumnDefault('tags', 'id'))->not->toContain('uuidv7')
            ->and(pgColumnDefault('taggables', 'id'))->not->toContain('uuidv7');
    });
});

describe('the upgrade migration, for installs built before the default existed', function (): void {
    it('adds the default to columns that lack it', function (): void {
        migrateOnPostgres(['taxon.id_type' => 'uuid7']);
        stripUuidDefaults();

        expect(pgColumnDefault('tags', 'id'))->toBeNull();

        migrateOneOnPostgres('default_uuidv7_to_taxon_ids', ['taxon.id_type' => 'uuid7']);

        expect(pgColumnDefault('tags', 'id'))->toContain('uuidv7()')
            ->and(pgColumnDefault('taggables', 'id'))->toContain('uuidv7()');
    });

    it('is safe to run twice', function (): void {
        migrateOnPostgres(['taxon.id_type' => 'uuid7']);
        stripUuidDefaults();

        migrateOneOnPostgres('default_uuidv7_to_taxon_ids', ['taxon.id_type' => 'uuid7']);
        migrateOneOnPostgres('default_uuidv7_to_taxon_ids', ['taxon.id_type' => 'uuid7']);

        expect(pgColumnDefault('tags', 'id'))->toContain('uuidv7()');
    });

    it('leaves a bigint install alone — a uuid default on a sequence-backed column would break every insert', function (): void {
        migrateOnPostgres(['taxon.id_type' => 'incrementing']);

        migrateOneOnPostgres('default_uuidv7_to_taxon_ids', ['taxon.id_type' => 'incrementing']);

        expect(pgColumnDefault('tags', 'id'))->not->toContain('uuidv7')
            ->and(pgColumnDefault('taggables', 'id'))->not->toContain('uuidv7');
    });
});

describe('taggable_id_type — the host application\'s key type, decoupled from the tag key type', function (): void {
    it('follows id_type when left null', function (): void {
        migrateOnPostgres(['taxon.id_type' => 'uuid7', 'taxon.taggable_id_type' => null]);

        expect(pgColumnType('taggables', 'taggable_id'))->toBe('uuid');
    });

    it('keeps taggable_id an integer while the tags themselves use uuid keys', function (): void {
        migrateOnPostgres(['taxon.id_type' => 'uuid7', 'taxon.taggable_id_type' => 'incrementing']);

        expect(pgColumnType('tags', 'id'))->toBe('uuid')
            ->and(pgColumnType('taggables', 'taggable_id'))->toBe('bigint');
    });

    it('keeps taggable_id a uuid while the tags themselves use incrementing keys', function (): void {
        migrateOnPostgres(['taxon.id_type' => 'incrementing', 'taxon.taggable_id_type' => 'uuid7']);

        expect(pgColumnType('tags', 'id'))->toBe('bigint')
            ->and(pgColumnType('taggables', 'taggable_id'))->toBe('uuid');
    });
});
