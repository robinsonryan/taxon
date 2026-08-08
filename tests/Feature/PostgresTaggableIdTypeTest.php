<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Why this file talks to a real PostgreSQL server
|--------------------------------------------------------------------------
|
| The rest of the suite runs on SQLite :memory:, where every one of the column
| types below compiles down to a loosely typed `varchar` and an integer written
| into a "uuid" column is silently accepted. That blindness is precisely how a
| `uuid`-typed `taggable_id` shipped as the default schema. These assertions only
| carry information against a driver with real column types, so they run against
| the DDEV PostgreSQL service and skip — loudly — anywhere else.
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

beforeEach(function (): void {
    config()->set('database.connections.' . PG_CONNECTION, pgConnectionConfig());

    try {
        DB::connection(PG_CONNECTION)->getPdo();
    } catch (Throwable $e) {
        $this->markTestSkipped(
            'No PostgreSQL server reachable (' . $e->getMessage() . '). Run this suite inside DDEV.'
        );
    }

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
