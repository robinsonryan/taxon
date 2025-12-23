<?php

use RobinsonRyan\Taxon\Models\Tag;

describe('Tag Model', function () {
    it('can be created with name and slug', function () {
        $tag = Tag::create([
            'name' => 'Test Tag',
            'slug' => 'test-tag',
        ]);

        expect($tag)->toBeInstanceOf(Tag::class)
            ->and($tag->name)->toBe('Test Tag')
            ->and($tag->slug)->toBe('test-tag');
    });

    it('auto-generates slug from name if not provided', function () {
        $tag = Tag::create(['name' => 'My Awesome Tag']);

        expect($tag->slug)->toBe('my-awesome-tag');
    });

    it('has default values for assignable and single_select', function () {
        $tag = Tag::create(['name' => 'Test']);

        expect($tag->assignable)->toBeTrue()
            ->and($tag->single_select)->toBeTrue();
    });

    it('can have a parent tag', function () {
        $parent = Tag::create(['name' => 'Status']);
        $child = Tag::create([
            'name' => 'Pending',
            'parent_id' => $parent->id,
        ]);

        expect($child->parent->id)->toBe($parent->id);
    });

    it('can have children tags', function () {
        $parent = Tag::create(['name' => 'Status']);
        Tag::create(['name' => 'Pending', 'parent_id' => $parent->id]);
        Tag::create(['name' => 'Complete', 'parent_id' => $parent->id]);

        expect($parent->children)->toHaveCount(2);
    });

    it('is a root tag when parent_id is null', function () {
        $root = Tag::create(['name' => 'Status']);
        $child = Tag::create(['name' => 'Pending', 'parent_id' => $root->id]);

        expect($root->isRoot())->toBeTrue()
            ->and($child->isRoot())->toBeFalse();
    });

    it('is a category when it has children', function () {
        $category = Tag::create(['name' => 'Status']);
        Tag::create(['name' => 'Pending', 'parent_id' => $category->id]);

        $loneTag = Tag::create(['name' => 'Ideas']);

        expect($category->fresh()->isCategory())->toBeTrue()
            ->and($loneTag->isCategory())->toBeFalse();
    });

    it('casts meta to array', function () {
        $tag = Tag::create([
            'name' => 'Test',
            'meta' => ['color' => 'red', 'icon' => 'star'],
        ]);

        expect($tag->meta)->toBeArray()
            ->and($tag->meta['color'])->toBe('red');
    });

    it('cascades delete to children', function () {
        $parent = Tag::create(['name' => 'Status']);
        $child1 = Tag::create(['name' => 'Pending', 'parent_id' => $parent->id]);
        $child2 = Tag::create(['name' => 'Complete', 'parent_id' => $parent->id]);

        $parent->delete();

        expect(Tag::find($child1->id))->toBeNull()
            ->and(Tag::find($child2->id))->toBeNull();
    });
});

describe('Tag Query Scopes', function () {
    beforeEach(function () {
        $this->status = Tag::create(['name' => 'Status', 'assignable' => false]);
        Tag::create(['name' => 'Pending', 'parent_id' => $this->status->id]);
        Tag::create(['name' => 'Complete', 'parent_id' => $this->status->id]);
        $this->ideas = Tag::create(['name' => 'Ideas']);
    });

    it('scopes to root tags', function () {
        $roots = Tag::roots()->get();

        expect($roots)->toHaveCount(2)
            ->and($roots->pluck('slug')->toArray())->toContain('status', 'ideas');
    });

    it('scopes to categories', function () {
        $categories = Tag::categories()->get();

        expect($categories)->toHaveCount(1)
            ->and($categories->first()->slug)->toBe('status');
    });

    it('scopes to assignable tags', function () {
        $assignable = Tag::assignable()->get();

        expect($assignable)->toHaveCount(3); // pending, complete, ideas
    });

    it('scopes by slug', function () {
        $tag = Tag::slug('pending')->first();

        expect($tag->name)->toBe('Pending');
    });

    it('scopes children of a category', function () {
        $children = Tag::childrenOf('status')->get();

        expect($children)->toHaveCount(2);
    });
});
