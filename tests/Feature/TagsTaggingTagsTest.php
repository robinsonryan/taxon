<?php

use RobinsonRyan\Taxon\HasTags;
use RobinsonRyan\Taxon\Models\Tag;

describe('Tags Tagging Tags', function () {
    beforeEach(function () {
        // Create Roles category
        $this->roles = Tag::createCategory('Roles');
        $this->adminRole = $this->roles->addChild('admin');
        $this->editorRole = $this->roles->addChild('editor');

        // Create Permissions category
        $this->permissions = Tag::createCategory('Permissions');
        $this->createPost = $this->permissions->addChild('posts-create');
        $this->readPost = $this->permissions->addChild('posts-read');
        $this->deletePost = $this->permissions->addChild('posts-delete');
    });

    it('can tag a tag with another tag', function () {
        // Admin role gets permissions
        $this->adminRole->tag('posts-create');

        expect($this->adminRole->tags)->toHaveCount(1)
            ->and($this->adminRole->hasTag('posts-create'))->toBeTrue();
    });

    it('role can have multiple permission tags', function () {
        $this->adminRole->tag(['posts-create', 'posts-read', 'posts-delete']);

        expect($this->adminRole->tags)->toHaveCount(3);
    });

    it('can query permissions for a role', function () {
        $this->adminRole->tag(['posts-create', 'posts-read', 'posts-delete']);
        $this->editorRole->tag(['posts-create', 'posts-read']);

        $adminPerms = $this->adminRole->tags->pluck('slug')->toArray();
        $editorPerms = $this->editorRole->tags->pluck('slug')->toArray();

        expect($adminPerms)->toContain('posts-delete')
            ->and($editorPerms)->not->toContain('posts-delete');
    });

    it('can sync permissions on a role', function () {
        $this->adminRole->tag(['posts-create', 'posts-read']);
        $this->adminRole->retag(['posts-delete']);

        expect($this->adminRole->tags)->toHaveCount(1)
            ->and($this->adminRole->hasTag('posts-delete'))->toBeTrue();
    });

    it('Tag model uses HasTags trait', function () {
        expect(class_uses(Tag::class))->toContain(HasTags::class);
    });
});
