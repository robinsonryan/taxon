<?php

use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusEnum;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModelWithAttributes;

describe('Magic Attribute Access - String Categories', function () {
    beforeEach(function () {
        Tag::createCategory('Status', singleSelect: true)
            ->addChildren(['pending', 'complete', 'archived']);

        $this->model = TestModelWithAttributes::create(['name' => 'Test']);
    });

    it('can get tag value as property', function () {
        $this->model->setTag('status', 'pending');

        expect($this->model->status)->toBe('pending');
    });

    it('can set tag value as property', function () {
        $this->model->status = 'complete';

        expect($this->model->getTagValueIn('status'))->toBe('complete');
    });

    it('returns null when no tag set', function () {
        expect($this->model->status)->toBeNull();
    });

    it('replaces existing value on set', function () {
        $this->model->status = 'pending';
        $this->model->status = 'complete';

        expect($this->model->status)->toBe('complete')
            ->and($this->model->tagsIn('status'))->toHaveCount(1);
    });
});

describe('Magic Attribute Access - TagDefinition Backed', function () {
    beforeEach(function () {
        // Ensure the definition tag exists
        StatusDefinition::tag();
        StatusDefinition::valueTag(StatusEnum::PENDING);
        StatusDefinition::valueTag(StatusEnum::APPROVED);

        $this->model = TestModelWithAttributes::create(['name' => 'Test']);
    });

    it('can get typed enum value as property', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING);

        expect($this->model->priority)->toBe(StatusEnum::PENDING);
    });

    it('can set enum value as property', function () {
        $this->model->priority = StatusEnum::APPROVED;

        expect($this->model->getTagAs(StatusDefinition::class))->toBe(StatusEnum::APPROVED);
    });

    it('can set string value that maps to enum', function () {
        $this->model->priority = 'pending';

        expect($this->model->getTagAs(StatusDefinition::class))->toBe(StatusEnum::PENDING);
    });
});

describe('Magic Attributes - Non-Tag Attributes', function () {
    it('does not interfere with regular model attributes', function () {
        $model = TestModelWithAttributes::create(['name' => 'Original']);

        expect($model->name)->toBe('Original');

        $model->name = 'Updated';
        expect($model->name)->toBe('Updated');
    });

    it('does not interfere with model without tagAttributes', function () {
        $model = TestModel::create(['name' => 'Test']);

        expect($model->name)->toBe('Test');

        // This should not try to resolve as a tag
        expect($model->nonexistent ?? 'default')->toBe('default');
    });
});
