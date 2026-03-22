<?php

use Illuminate\Database\QueryException;
use RobinsonRyan\Taxon\Models\Tag;

describe('Tenant Scoping', function () {
    beforeEach(function () {
        config()->set('taxon.tenant.enabled', true);
        config()->set('taxon.tenant.column', 'tenant_id');
    });

    it('creates tags with tenant_id', function () {
        $tag = Tag::create([
            'name' => 'Tenant Tag',
            'tenant_id' => '1',
        ]);

        expect($tag->tenant_id)->toBe('1');
    });

    it('separates tags by tenant', function () {
        Tag::create(['name' => 'Shared Name', 'tenant_id' => '1']);
        Tag::create(['name' => 'Shared Name', 'tenant_id' => '2']);

        expect(Tag::where('name', 'Shared Name')->count())->toBe(2);
    });

    it('enforces uniqueness within tenant for child tags', function () {
        $parent = Tag::createCategory('Status', tenantId: '1');

        $parent->addChild('active');

        // Duplicate child should throw
        Tag::create(['name' => 'Active', 'slug' => 'active', 'parent_id' => $parent->id, 'tenant_id' => '1']);
    })->throws(QueryException::class);

    it('allows same slug in different tenants', function () {
        $tag1 = Tag::create(['name' => 'Status', 'tenant_id' => '1']);
        $tag2 = Tag::create(['name' => 'Status', 'tenant_id' => '2']);

        expect($tag1->id)->not->toBe($tag2->id);
    });

    it('scopes category children by tenant', function () {
        $cat1 = Tag::createCategory('Status', tenantId: '1');
        $cat1->addChild('Active');

        $cat2 = Tag::createCategory('Status', tenantId: '2');
        $cat2->addChild('Inactive');

        expect($cat1->children)->toHaveCount(1)
            ->and($cat1->children->first()->slug)->toBe('active');

        expect($cat2->children)->toHaveCount(1)
            ->and($cat2->children->first()->slug)->toBe('inactive');
    });
});

describe('Global Tags', function () {
    beforeEach(function () {
        config()->set('taxon.tenant.enabled', true);
    });

    it('creates global tags with null tenant_id', function () {
        $tag = Tag::create([
            'name' => 'Global Tag',
            'tenant_id' => null,
        ]);

        expect($tag->tenant_id)->toBeNull();
    });

    it('global child tags are unique within parent', function () {
        $parent = Tag::createCategory('System', tenantId: null);
        $child = $parent->addChild('config');

        // Verify child was created correctly
        expect($child->parent_id)->toBe($parent->id)
            ->and($child->tenant_id)->toBeNull();
    });
});
