<?php

use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;

describe('Direct Tagging', function () {
    beforeEach(function () {
        $this->model = TestModel::create(['name' => 'Test']);
    });

    it('can tag a model with a string', function () {
        $this->model->tag('important');

        expect($this->model->tags)->toHaveCount(1)
            ->and($this->model->tags->first()->slug)->toBe('important');
    });

    it('auto-creates tag if it does not exist', function () {
        expect(Tag::where('slug', 'new-tag')->exists())->toBeFalse();

        $this->model->tag('new-tag');

        expect(Tag::where('slug', 'new-tag')->exists())->toBeTrue();
    });

    it('can tag with multiple strings', function () {
        $this->model->tag(['idea', 'todo', 'urgent']);

        expect($this->model->tags)->toHaveCount(3);
    });

    it('does not duplicate tags', function () {
        $this->model->tag('important');
        $this->model->tag('important');

        expect($this->model->tags)->toHaveCount(1);
    });

    it('can untag a model', function () {
        $this->model->tag(['a', 'b', 'c']);
        $this->model->untag('b');

        expect($this->model->tags)->toHaveCount(2)
            ->and($this->model->tags->pluck('slug')->toArray())->not->toContain('b');
    });

    it('can untag multiple', function () {
        $this->model->tag(['a', 'b', 'c']);
        $this->model->untag(['a', 'c']);

        expect($this->model->tags)->toHaveCount(1)
            ->and($this->model->tags->first()->slug)->toBe('b');
    });

    it('can retag (sync) a model', function () {
        $this->model->tag(['a', 'b', 'c']);
        $this->model->retag(['x', 'y']);

        expect($this->model->tags)->toHaveCount(2)
            ->and($this->model->tags->pluck('slug')->toArray())->toBe(['x', 'y']);
    });

    it('can detach all tags', function () {
        $this->model->tag(['a', 'b', 'c']);
        $this->model->detachAllTags();

        expect($this->model->tags)->toHaveCount(0);
    });
});

describe('Direct Tag Checks', function () {
    beforeEach(function () {
        $this->model = TestModel::create(['name' => 'Test']);
        $this->model->tag(['alpha', 'beta', 'gamma']);
    });

    it('checks if model has a tag', function () {
        expect($this->model->hasTag('alpha'))->toBeTrue()
            ->and($this->model->hasTag('delta'))->toBeFalse();
    });

    it('checks if model has any of given tags', function () {
        expect($this->model->hasAnyTag(['alpha', 'delta']))->toBeTrue()
            ->and($this->model->hasAnyTag(['delta', 'epsilon']))->toBeFalse();
    });

    it('checks if model has all given tags', function () {
        expect($this->model->hasAllTags(['alpha', 'beta']))->toBeTrue()
            ->and($this->model->hasAllTags(['alpha', 'delta']))->toBeFalse();
    });
});

describe('Direct Tag Query Scopes', function () {
    beforeEach(function () {
        $this->m1 = TestModel::create(['name' => 'M1']);
        $this->m2 = TestModel::create(['name' => 'M2']);
        $this->m3 = TestModel::create(['name' => 'M3']);

        $this->m1->tag(['php', 'laravel']);
        $this->m2->tag(['php', 'vue']);
        $this->m3->tag(['javascript', 'vue']);
    });

    it('scopes models with a specific tag', function () {
        $models = TestModel::withTag('php')->get();

        expect($models)->toHaveCount(2)
            ->and($models->pluck('name')->toArray())->toContain('M1', 'M2');
    });

    it('scopes models with any of given tags', function () {
        $models = TestModel::withAnyTag(['laravel', 'vue'])->get();

        expect($models)->toHaveCount(3);
    });

    it('scopes models with all given tags', function () {
        $models = TestModel::withAllTags(['php', 'vue'])->get();

        expect($models)->toHaveCount(1)
            ->and($models->first()->name)->toBe('M2');
    });

    it('scopes models without a specific tag', function () {
        $models = TestModel::withoutTag('php')->get();

        expect($models)->toHaveCount(1)
            ->and($models->first()->name)->toBe('M3');
    });
});
