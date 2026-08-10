<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RobinsonRyan\Taxon\Exceptions\CircularTagHierarchyException;
use RobinsonRyan\Taxon\Exceptions\DuplicateTagSlugException;
use RobinsonRyan\Taxon\Models\Tag;

/*
|--------------------------------------------------------------------------
| Arbitrary-depth trees
|--------------------------------------------------------------------------
|
| HasTags' category API is deliberately two levels deep (a category and its
| values). These are the direct-Tag operations that are not: a tag addressed by
| its slug path, its ancestors and descendants at any depth, and re-parenting
| with a cycle guard.
|
*/

function buildTopicTree(?string $tenantId = null): Tag
{
    $topics = Tag::createCategory('Topics', $tenantId);
    $web = Tag::createValue('Web', $topics->id, $tenantId);
    $frontend = Tag::createValue('Frontend', $web->id, $tenantId);
    Tag::createValue('Vue', $frontend->id, $tenantId);

    return $topics;
}

describe('a tag knows its slug path', function (): void {
    it('is just the slug for a root tag', function (): void {
        expect(buildTopicTree()->path())->toBe('topics');
    });

    it('joins every slug from the root down to the tag', function (): void {
        buildTopicTree();

        $vue = Tag::where('slug', 'vue')->firstOrFail();

        expect($vue->path())->toBe('topics/web/frontend/vue');
    });
});

describe('a path resolves back to a tag', function (): void {
    it('walks the segments from a root tag', function (): void {
        buildTopicTree();

        $resolved = Tag::resolvePath('topics/web/frontend/vue');

        expect($resolved)->not->toBeNull()
            ->and($resolved->slug)->toBe('vue')
            ->and($resolved->path())->toBe('topics/web/frontend/vue');
    });

    it('returns null when a segment is missing', function (): void {
        buildTopicTree();

        expect(Tag::resolvePath('topics/web/backend'))->toBeNull();
    });

    it('refuses to start part-way down the tree', function (): void {
        buildTopicTree();

        expect(Tag::resolvePath('web/frontend'))->toBeNull();
    });

    it('slugs the segments it is given', function (): void {
        buildTopicTree();

        expect(Tag::resolvePath('/Topics/Web/Front End/')?->slug)->toBeNull()
            ->and(Tag::resolvePath('/Topics/Web/Frontend/')?->slug)->toBe('frontend');
    });

    it('returns null for an empty path', function (): void {
        buildTopicTree();

        expect(Tag::resolvePath('/'))->toBeNull()
            ->and(Tag::resolvePath(''))->toBeNull();
    });

    it('keeps one tenant\'s path out of another\'s', function (): void {
        buildTopicTree('tenant-a');
        buildTopicTree('tenant-b');

        $a = Tag::resolvePath('topics/web', 'tenant-a');
        $b = Tag::resolvePath('topics/web', 'tenant-b');

        expect($a)->not->toBeNull()
            ->and($b)->not->toBeNull()
            ->and($a->id)->not->toBe($b->id)
            ->and($a->tenant_id)->toBe('tenant-a');
    });

    it('treats an absent tenant as its own space, not as a wildcard', function (): void {
        buildTopicTree('tenant-a');

        expect(Tag::resolvePath('topics/web'))->toBeNull();
    });
});

describe('ancestors and descendants at any depth', function (): void {
    it('lists ancestors root-first', function (): void {
        buildTopicTree();
        $vue = Tag::where('slug', 'vue')->firstOrFail();

        expect($vue->ancestors()->pluck('slug')->all())->toBe(['topics', 'web', 'frontend']);
    });

    it('gives a root tag no ancestors', function (): void {
        $topics = buildTopicTree();

        expect($topics->ancestors()->all())->toBe([]);
    });

    it('lists the whole subtree, nearest level first', function (): void {
        $topics = buildTopicTree();
        Tag::createValue('Ops', $topics->id);

        expect($topics->descendants()->pluck('slug')->all())
            ->toBe(['web', 'ops', 'frontend', 'vue']);
    });

    it('gives a leaf tag no descendants', function (): void {
        buildTopicTree();
        $vue = Tag::where('slug', 'vue')->firstOrFail();

        expect($vue->descendants()->all())->toBe([]);
    });

    it('walks ancestors in a fixed number of queries, whatever the depth', function (): void {
        buildTopicTree();
        $vue = Tag::where('slug', 'vue')->firstOrFail();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $vue->ancestors();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($queries)->toBe(2);
    });

    it('walks descendants in a fixed number of queries, whatever the size', function (): void {
        $topics = buildTopicTree();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $topics->descendants();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($queries)->toBe(2);
    });
});

describe('re-parenting', function (): void {
    it('moves a tag and everything under it', function (): void {
        $topics = buildTopicTree();
        $ops = Tag::createValue('Ops', $topics->id);
        $frontend = Tag::where('slug', 'frontend')->firstOrFail();

        $frontend->moveTo($ops);

        expect($frontend->fresh()->path())->toBe('topics/ops/frontend')
            ->and(Tag::resolvePath('topics/ops/frontend/vue'))->not->toBeNull();
    });

    it('promotes a tag to a root', function (): void {
        buildTopicTree();
        $web = Tag::where('slug', 'web')->firstOrFail();

        $web->moveTo(null);

        expect($web->fresh()->parent_id)->toBeNull()
            ->and($web->fresh()->path())->toBe('web');
    });

    it('refuses to move a tag under itself', function (): void {
        buildTopicTree();
        $web = Tag::where('slug', 'web')->firstOrFail();

        $web->moveTo($web);
    })->throws(CircularTagHierarchyException::class);

    it('refuses to move a tag under its own descendant', function (): void {
        buildTopicTree();
        $web = Tag::where('slug', 'web')->firstOrFail();
        $vue = Tag::where('slug', 'vue')->firstOrFail();

        $web->moveTo($vue);
    })->throws(CircularTagHierarchyException::class);

    it('leaves the tag where it was when it refuses', function (): void {
        $topics = buildTopicTree();
        $web = Tag::where('slug', 'web')->firstOrFail();
        $vue = Tag::where('slug', 'vue')->firstOrFail();

        try {
            $web->moveTo($vue);
        } catch (CircularTagHierarchyException) {
            // expected
        }

        expect($web->fresh()->parent_id)->toBe($topics->id);
    });

    it('reports a slug collision as a domain error, not a query error', function (): void {
        $topics = buildTopicTree();
        $ops = Tag::createValue('Ops', $topics->id);
        Tag::createValue('Frontend', $ops->id);
        $frontend = Tag::resolvePath('topics/web/frontend');

        expect(fn (): Tag => $frontend->moveTo($ops))
            ->toThrow(DuplicateTagSlugException::class);

        expect(Tag::resolvePath('topics/web/frontend'))->not->toBeNull();
    });

    it('allows a slug that only collides in another tenant', function (): void {
        $topics = Tag::createCategory('Topics', 'tenant-a');
        $web = Tag::createValue('Web', $topics->id, 'tenant-a');
        $frontend = Tag::createValue('Frontend', $web->id, 'tenant-a');

        // Same slug, same destination parent, different tenant — the index lets
        // these coexist, so the pre-check has to as well.
        Tag::createValue('Frontend', $topics->id, 'tenant-b');

        $frontend->moveTo($topics);

        expect($frontend->fresh()->parent_id)->toBe($topics->id)
            ->and($frontend->fresh()->path())->toBe('topics/frontend');
    });

    it('is a no-op when the tag is already there', function (): void {
        $topics = buildTopicTree();
        $web = Tag::where('slug', 'web')->firstOrFail();

        $web->moveTo($topics);

        expect($web->fresh()->parent_id)->toBe($topics->id);
    });
});
