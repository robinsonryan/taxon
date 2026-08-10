<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| The de-duplication half of the uniqueness migration
|--------------------------------------------------------------------------
|
| Hardening the index is the easy half. The hard half is that consumers have
| been running without a working index, so their `tags` table may already hold
| the duplicates it is about to forbid — and a bare CREATE UNIQUE INDEX would
| fail their deployment. The migration collapses each duplicate group onto its
| oldest member first, carrying that group's pivot rows and child tags across.
|
| Like PostgresTaggableIdTypeTest, this file needs to drop and recreate `tags`
| and `taggables` to build the pre-migration state, so it works on its own
| connection in the `db` database rather than the shared `testing` schema that
| RefreshDatabase migrated once for everyone else.
|
*/

const DEDUP_CONNECTION = 'pgsql_dedup_probe';

/** @return list<string> */
function taxonMigrationFiles(): array
{
    $files = glob(__DIR__ . '/../../database/migrations/*.php');
    $files = $files === false ? [] : $files;
    sort($files);

    return array_values($files);
}

/** Run the package migrations at the given positions, in order, on the probe connection. */
function runTaxonMigrations(int $from, ?int $to = null): void
{
    $files = taxonMigrationFiles();
    $slice = array_slice($files, $from, $to === null ? null : $to - $from);

    foreach ($slice as $file) {
        $migration = require $file;

        if ($migration instanceof Migration) {
            $migration->up();
        }
    }
}

function dropDedupTables(): void
{
    $schema = Schema::connection(DEDUP_CONNECTION);

    $schema->dropIfExists('taggables');
    $schema->dropIfExists('tags');
}

function insertDedupTag(string $slug, ?int $parentId = null, ?string $tenantId = null): int
{
    return (int) DB::connection(DEDUP_CONNECTION)->table('tags')->insertGetId([
        'name' => ucfirst($slug),
        'slug' => $slug,
        'parent_id' => $parentId,
        'tenant_id' => $tenantId,
        'assignable' => true,
        'single_select' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function insertDedupTaggable(int $tagId, int $modelId): void
{
    DB::connection(DEDUP_CONNECTION)->table('taggables')->insert([
        'tag_id' => $tagId,
        'taggable_type' => 'test_model',
        'taggable_id' => $modelId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

beforeEach(function (): void {
    config()->set('database.connections.' . DEDUP_CONNECTION, [
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
    ]);

    try {
        DB::connection(DEDUP_CONNECTION)->getPdo();
    } catch (Throwable $e) {
        $this->markTestSkipped(
            'No PostgreSQL server reachable (' . $e->getMessage() . '). Run this suite inside DDEV.'
        );
    }

    dropDedupTables();

    config()->set('database.default', DEDUP_CONNECTION);
    config()->set('taxon.id_type', 'incrementing');

    // Everything up to, but not including, the uniqueness migration: this is the
    // schema a consumer is upgrading from, duplicates and all.
    runTaxonMigrations(0, count(taxonMigrationFiles()) - 1);
});

afterEach(function (): void {
    dropDedupTables();

    config()->set('database.default', 'testing');
});

it('accepts duplicate root tags before the migration — the bug it exists to close', function (): void {
    insertDedupTag('topics');
    insertDedupTag('topics');

    expect(DB::connection(DEDUP_CONNECTION)->table('tags')->where('slug', 'topics')->count())->toBe(2);
});

it('collapses a duplicate group onto its oldest member', function (): void {
    $keep = insertDedupTag('topics');
    $loser = insertDedupTag('topics');

    runTaxonMigrations(count(taxonMigrationFiles()) - 1);

    $survivors = DB::connection(DEDUP_CONNECTION)->table('tags')->where('slug', 'topics')->pluck('id')->all();

    expect($survivors)->toBe([$keep])
        ->and($survivors)->not->toContain($loser);
});

it('carries the loser\'s pivot rows across to the survivor', function (): void {
    $keep = insertDedupTag('topics');
    $loser = insertDedupTag('topics');
    insertDedupTaggable($loser, 42);

    runTaxonMigrations(count(taxonMigrationFiles()) - 1);

    $rows = DB::connection(DEDUP_CONNECTION)->table('taggables')->get();

    expect($rows)->toHaveCount(1)
        ->and((int) $rows->first()->tag_id)->toBe($keep);
});

it('drops a pivot row that collapsing would have duplicated', function (): void {
    $keep = insertDedupTag('topics');
    $loser = insertDedupTag('topics');
    insertDedupTaggable($keep, 42);
    insertDedupTaggable($loser, 42);

    runTaxonMigrations(count(taxonMigrationFiles()) - 1);

    expect(DB::connection(DEDUP_CONNECTION)->table('taggables')->count())->toBe(1);
});

it('re-parents the children of a collapsed duplicate', function (): void {
    $keep = insertDedupTag('topics');
    $loser = insertDedupTag('topics');
    insertDedupTag('php', $keep);
    insertDedupTag('laravel', $loser);

    runTaxonMigrations(count(taxonMigrationFiles()) - 1);

    $children = DB::connection(DEDUP_CONNECTION)->table('tags')
        ->where('parent_id', $keep)
        ->pluck('slug')
        ->sort()
        ->values()
        ->all();

    expect($children)->toBe(['laravel', 'php']);
});

it('collapses children that collide only after their parents are merged', function (): void {
    $keep = insertDedupTag('topics');
    $loser = insertDedupTag('topics');
    insertDedupTag('php', $keep);
    insertDedupTag('php', $loser);

    runTaxonMigrations(count(taxonMigrationFiles()) - 1);

    expect(DB::connection(DEDUP_CONNECTION)->table('tags')->count())->toBe(2);
});

it('rejects the duplicate once the migration has run', function (): void {
    insertDedupTag('topics');

    runTaxonMigrations(count(taxonMigrationFiles()) - 1);

    expect(fn (): int => insertDedupTag('topics'))
        ->toThrow(QueryException::class);
});

it('adds the parent_id index the tree walks depend on', function (): void {
    runTaxonMigrations(count(taxonMigrationFiles()) - 1);

    $indexes = DB::connection(DEDUP_CONNECTION)->table('pg_indexes')
        ->where('tablename', 'tags')
        ->pluck('indexname')
        ->all();

    expect($indexes)->toContain('tags_parent_id_index');
});

it('is reversible back to the schema it replaced', function (): void {
    runTaxonMigrations(count(taxonMigrationFiles()) - 1);

    $files = taxonMigrationFiles();
    $migration = require $files[count($files) - 1];
    expect($migration)->toBeInstanceOf(Migration::class);
    $migration->down();

    $indexes = DB::connection(DEDUP_CONNECTION)->table('pg_indexes')
        ->where('tablename', 'tags')
        ->pluck('indexdef', 'indexname')
        ->all();

    expect($indexes)->not->toHaveKey('tags_parent_id_index')
        ->and($indexes['tags_unique_slug_parent_tenant'])->not->toContain('COALESCE');
});

/*
|--------------------------------------------------------------------------
| Driver support
|--------------------------------------------------------------------------
|
| The index this migration builds needs functional key parts. PostgreSQL and
| SQLite take an expression in an index directly; MySQL grew the double-paren
| form in 8.0.13; MariaDB has never supported it at all. Getting that wrong is
| not a cosmetic failure — up() drops the old constraint and de-duplicates
| before it creates the new index, so a CREATE that fails leaves the table with
| no uniqueness at all and a half-applied migration. It therefore refuses
| up-front, before touching anything.
|
*/

function lastTaxonMigration(): object
{
    $files = taxonMigrationFiles();

    return require $files[count($files) - 1];
}

it('refuses to run on MariaDB', function (): void {
    expect(fn () => lastTaxonMigration()->assertDriverSupported('mariadb', '10.11.6'))
        ->toThrow(RuntimeException::class, 'MariaDB');
});

it('refuses a MariaDB server reported through the mysql driver', function (): void {
    expect(fn () => lastTaxonMigration()->assertDriverSupported('mysql', '10.11.6-MariaDB'))
        ->toThrow(RuntimeException::class, 'MariaDB');
});

it('refuses MySQL older than functional key parts', function (): void {
    expect(fn () => lastTaxonMigration()->assertDriverSupported('mysql', '5.7.44'))
        ->toThrow(RuntimeException::class, '8.0.13');
});

it('accepts the drivers that can build the index', function (): void {
    $migration = lastTaxonMigration();

    $migration->assertDriverSupported('pgsql');
    $migration->assertDriverSupported('sqlite');
    $migration->assertDriverSupported('mysql', '8.0.13');
    $migration->assertDriverSupported('mysql', '8.4.2');

    expect(true)->toBeTrue();
});

it('refuses a driver it has never been run against', function (): void {
    expect(fn () => lastTaxonMigration()->assertDriverSupported('sqlsrv', '16.0'))
        ->toThrow(RuntimeException::class, 'sqlsrv');
});

it('names the migration and points at the docs, so the message is actionable', function (): void {
    expect(fn () => lastTaxonMigration()->assertDriverSupported('mariadb', '11.4.2'))
        ->toThrow(RuntimeException::class, 'docs/installation.md');
});

it('drops nothing when it refuses the driver', function (): void {
    config()->set('database.connections.taxon_unsupported_probe', [
        'driver' => 'sqlsrv',
        'host' => '127.0.0.1',
        'database' => 'nowhere',
        'username' => 'nobody',
        'password' => '',
        'prefix' => '',
    ]);
    config()->set('database.default', 'taxon_unsupported_probe');

    expect(fn () => lastTaxonMigration()->up())->toThrow(RuntimeException::class);

    config()->set('database.default', DEDUP_CONNECTION);

    $indexes = DB::connection(DEDUP_CONNECTION)->table('pg_indexes')
        ->where('tablename', 'tags')
        ->pluck('indexname')
        ->all();

    expect($indexes)->toContain('tags_unique_slug_parent_tenant');
});
