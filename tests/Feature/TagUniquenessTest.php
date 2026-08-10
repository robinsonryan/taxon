<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RobinsonRyan\Taxon\Models\Tag;

/*
|--------------------------------------------------------------------------
| The uniqueness the schema actually enforces
|--------------------------------------------------------------------------
|
| `tags_unique_slug_parent_tenant` was a plain composite unique over
| (slug, parent_id, tenant_id). NULLs are distinct in a unique index on every
| driver, so it enforced nothing for a root tag (parent_id IS NULL) or a global
| tag (tenant_id IS NULL) — which is most of them. These tests hold the
| expression index that replaced it to the guarantee the column list only
| implied.
|
| Each violating write goes through DB::transaction() so the failed statement
| rolls back to a savepoint: PostgreSQL aborts a transaction on error, and the
| suite's own RefreshDatabase transaction has to survive the test.
|
*/

describe('duplicate tags are rejected where NULL used to hide them', function (): void {
    it('rejects a second root tag with the same slug', function (): void {
        Tag::createCategory('Topics');

        expect(fn () => DB::transaction(fn (): Tag => Tag::createCategory('Topics')))
            ->toThrow(QueryException::class);
    });

    it('rejects a second global child with the same slug under one parent', function (): void {
        $parent = Tag::createCategory('System');
        $parent->addChild('config');

        expect(fn () => DB::transaction(fn (): Tag => $parent->addChild('config')))
            ->toThrow(QueryException::class);
    });

    it('rejects a second child with the same slug within one tenant', function (): void {
        $parent = Tag::createCategory('System', 'tenant-a');
        Tag::createValue('Config', $parent->id, 'tenant-a');

        expect(fn () => DB::transaction(fn (): Tag => Tag::createValue('Config', $parent->id, 'tenant-a')))
            ->toThrow(QueryException::class);
    });
});

describe('the index still allows what the tree is supposed to allow', function (): void {
    it('allows the same slug under two different parents', function (): void {
        $first = Tag::createCategory('Status');
        $second = Tag::createCategory('Stage');

        $first->addChild('open');
        $second->addChild('open');

        expect(Tag::where('slug', 'open')->count())->toBe(2);
    });

    it('allows the same root slug in two different tenants', function (): void {
        Tag::createCategory('Topics', 'tenant-a');
        Tag::createCategory('Topics', 'tenant-b');
        Tag::createCategory('Topics');

        expect(Tag::where('slug', 'topics')->count())->toBe(3);
    });
});

describe('the schema carries the indexes the queries need', function (): void {
    it('builds the uniqueness over COALESCE expressions, not bare columns', function (): void {
        $definition = DB::table('pg_indexes')
            ->where('tablename', config('taxon.tables.tags', 'tags'))
            ->where('indexname', 'tags_unique_slug_parent_tenant')
            ->value('indexdef');

        expect($definition)->toBeString()
            ->and($definition)->toContain('UNIQUE')
            ->and($definition)->toContain('COALESCE');
    });

    it('indexes parent_id, which every child lookup filters on', function (): void {
        $indexes = DB::table('pg_indexes')
            ->where('tablename', config('taxon.tables.tags', 'tags'))
            ->pluck('indexname')
            ->all();

        expect($indexes)->toContain('tags_parent_id_index');
    });
});
