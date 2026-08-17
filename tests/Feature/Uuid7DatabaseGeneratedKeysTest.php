<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Support\SchemaSupport;

/*
|--------------------------------------------------------------------------
| uuid7 mode, exercised through Eloquent
|--------------------------------------------------------------------------
|
| Until 0.5.0 nothing here was covered. The shipped default is
| `taxon.id_type => incrementing`, the shared `testing` schema RefreshDatabase
| builds is a bigint schema, and the only uuid7 coverage in the suite read column
| types out of the catalog and inserted through the query builder. So the whole
| uuid7 branch of the model layer — key generation, key round-trip, pivot writes
| — had never once been run.
|
| Like the other schema-varying files, this one needs `tags` and `taggables`
| built a different way per run, so it works on its own connection in the `db`
| database rather than on the shared `testing` schema.
|
*/

const UUID7_CONNECTION = 'pgsql_uuid7_probe';

/** @return array<string, mixed> */
function uuid7ConnectionConfig(): array
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

function dropUuid7Tables(): void
{
    $schema = Schema::connection(UUID7_CONNECTION);

    $schema->dropIfExists('uuid7_test_models');
    $schema->dropIfExists('taggables');
    $schema->dropIfExists('tags');
}

/**
 * Build a full uuid7 install: the package's own tables, plus a uuid-keyed
 * consumer table that keys itself the way the doctrine says a consumer should —
 * from the database, never from PHP.
 */
function migrateUuid7Schema(): void
{
    config()->set('taxon.id_type', 'uuid7');
    config()->set('database.default', UUID7_CONNECTION);

    $files = glob(__DIR__ . '/../../database/migrations/*.php');
    $files = $files === false ? [] : $files;
    sort($files);

    foreach ($files as $file) {
        $migration = require $file;

        if ($migration instanceof Migration) {
            $migration->up();
        }
    }

    Schema::connection(UUID7_CONNECTION)->create('uuid7_test_models', function (Blueprint $table): void {
        $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
        $table->string('name');
        $table->timestamps();
    });
}

beforeEach(function (): void {
    config()->set('database.connections.' . UUID7_CONNECTION, uuid7ConnectionConfig());

    try {
        DB::connection(UUID7_CONNECTION)->getPdo();
    } catch (Throwable $e) {
        $this->markTestSkipped(
            'No PostgreSQL server reachable (' . $e->getMessage() . '). Run this suite inside DDEV.'
        );
    }

    SchemaSupport::flushCapabilityCache();

    dropUuid7Tables();
    migrateUuid7Schema();
});

afterEach(function (): void {
    dropUuid7Tables();

    // migrateUuid7Schema() repoints the default connection; hand it back, or the
    // framework's own teardown looks for a migrations table on PostgreSQL.
    config()->set('database.default', 'testing');
    config()->set('taxon.id_type', 'incrementing');
});

describe('the key comes from the database', function (): void {
    it('hands back the generated key, so create() is usable', function (): void {
        $tag = Tag::create(['name' => 'Alpha']);

        expect($tag->getKey())->toBeUuidV7();
    });

    it('hands back the key the row actually holds', function (): void {
        $tag = Tag::create(['name' => 'Alpha']);

        $stored = DB::connection(UUID7_CONNECTION)->table('tags')->where('slug', 'alpha')->value('id');

        expect($tag->getKey())->toBe($stored);
    });

    it('tells Eloquent the key is a string the database assigns', function (): void {
        $tag = new Tag;

        expect($tag->getKeyType())->toBe('string')
            ->and($tag->getIncrementing())->toBeTrue();
    });

    it('leaves the key unset until the insert, so the column default can fire', function (): void {
        $tag = new Tag(['name' => 'Alpha']);

        expect($tag->getKey())->toBeNull();
    });

    /*
     * The decisive one. Take the column default away and the insert must fail:
     * if anything in PHP were still minting a key, it would sail through.
     */
    it('generates nothing in PHP — without the column default the insert fails', function (): void {
        DB::connection(UUID7_CONNECTION)->statement('alter table tags alter column id drop default');

        expect(fn (): Tag => Tag::create(['name' => 'Alpha']))->toThrow(QueryException::class);
    });

    it('gives a child tag a key of its own, and links it to its parent', function (): void {
        $category = Tag::createCategory('Status');
        $value = $category->addChild('Pending');

        expect($value->getKey())->toBeUuidV7()
            ->and($value->parent_id)->toBe($category->getKey());
    });
});
