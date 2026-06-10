<?php

use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;

beforeEach(function (): void {
    // Create status category
    $this->status = Tag::createCategory('Status', singleSelect: true);
    $this->status->addChildren(['pending', 'in-review', 'complete']);

    // Create equipment category (multi-select)
    $this->equipment = Tag::createCategory('Equipment', singleSelect: false);
    $this->equipment->addChildren(['weights', 'treadmill', 'bosu']);

    $this->model = TestModel::create(['name' => 'Test']);
});

describe('setTag (Single-Select)', function (): void {
    it('assigns a tag within a category', function (): void {
        $this->model->setTag('status', 'pending');

        expect($this->model->getTagIn('status'))->not->toBeNull()
            ->and($this->model->getTagIn('status')->slug)->toBe('pending');
    });

    it('replaces existing tag in category', function (): void {
        $this->model->setTag('status', 'pending');
        $this->model->setTag('status', 'complete');

        expect($this->model->tagsIn('status'))->toHaveCount(1)
            ->and($this->model->getTagIn('status')->slug)->toBe('complete');
    });

    it('returns the tag value as string', function (): void {
        $this->model->setTag('status', 'pending');

        expect($this->model->getTagValueIn('status'))->toBe('pending');
    });

    it('returns null if no tag in category', function (): void {
        expect($this->model->getTagIn('status'))->toBeNull()
            ->and($this->model->getTagValueIn('status'))->toBeNull();
    });
});

describe('addTag (Multi-Select)', function (): void {
    it('adds a tag within a category', function (): void {
        $this->model->addTag('equipment', 'weights');

        expect($this->model->tagsIn('equipment'))->toHaveCount(1);
    });

    it('accumulates tags in category', function (): void {
        $this->model->addTag('equipment', 'weights');
        $this->model->addTag('equipment', 'bosu');

        expect($this->model->tagsIn('equipment'))->toHaveCount(2)
            ->and($this->model->tagsIn('equipment')->pluck('slug')->toArray())
            ->toContain('weights', 'bosu');
    });

    it('can add multiple tags at once', function (): void {
        $this->model->addTags('equipment', ['weights', 'treadmill']);

        expect($this->model->tagsIn('equipment'))->toHaveCount(2);
    });

    it('does not duplicate tags', function (): void {
        $this->model->addTag('equipment', 'weights');
        $this->model->addTag('equipment', 'weights');

        expect($this->model->tagsIn('equipment'))->toHaveCount(1);
    });
});

describe('removeTag', function (): void {
    it('removes a specific tag from category', function (): void {
        $this->model->addTags('equipment', ['weights', 'bosu']);
        $this->model->removeTag('equipment', 'weights');

        expect($this->model->tagsIn('equipment'))->toHaveCount(1)
            ->and($this->model->tagsIn('equipment')->first()->slug)->toBe('bosu');
    });

    it('removes all tags from category when no value specified', function (): void {
        $this->model->addTags('equipment', ['weights', 'bosu']);
        $this->model->removeTagsIn('equipment');

        expect($this->model->tagsIn('equipment'))->toHaveCount(0);
    });
});

describe('hasTagIn Checks', function (): void {
    beforeEach(function (): void {
        $this->model->setTag('status', 'pending');
        $this->model->addTags('equipment', ['weights', 'bosu']);
    });

    it('checks if model has tag in category', function (): void {
        expect($this->model->hasTagIn('status', 'pending'))->toBeTrue()
            ->and($this->model->hasTagIn('status', 'complete'))->toBeFalse();
    });

    it('checks if model has any tag in category', function (): void {
        expect($this->model->hasAnyTagIn('equipment', ['weights', 'treadmill']))->toBeTrue()
            ->and($this->model->hasAnyTagIn('equipment', ['treadmill']))->toBeFalse();
    });

    it('checks if model has all tags in category', function (): void {
        expect($this->model->hasAllTagsIn('equipment', ['weights', 'bosu']))->toBeTrue()
            ->and($this->model->hasAllTagsIn('equipment', ['weights', 'treadmill']))->toBeFalse();
    });
});

describe('Category Query Scopes', function (): void {
    beforeEach(function (): void {
        $this->m1 = TestModel::create(['name' => 'M1']);
        $this->m2 = TestModel::create(['name' => 'M2']);
        $this->m3 = TestModel::create(['name' => 'M3']);

        $this->m1->setTag('status', 'pending');
        $this->m2->setTag('status', 'complete');
        $this->m3->setTag('status', 'pending');
    });

    it('scopes models with tag in category', function (): void {
        $models = TestModel::withTagIn('status', 'pending')->get();

        expect($models)->toHaveCount(2)
            ->and($models->pluck('name')->toArray())->toContain('M1', 'M3');
    });

    it('scopes models with any tag in category', function (): void {
        $models = TestModel::withAnyTagIn('status', ['pending', 'in-review'])->get();

        expect($models)->toHaveCount(2);
    });

    it('scopes models without tag in category', function (): void {
        $models = TestModel::withoutTagIn('status', 'pending')->get();

        // M2 has complete, not pending. $this->model from global beforeEach has no status tag.
        expect($models)->toHaveCount(2)
            ->and($models->pluck('name')->toArray())->toContain('M2');
    });
});
