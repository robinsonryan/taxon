<?php

use Illuminate\Support\Facades\DB;
use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusEnum;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModelWithAttributes;

describe('Magic Attribute Access - String Categories', function (): void {
    beforeEach(function (): void {
        Tag::createCategory('Status', singleSelect: true)
            ->addChildren(['pending', 'complete', 'archived']);

        $this->model = TestModelWithAttributes::create(['name' => 'Test']);
    });

    it('can get tag value as property', function (): void {
        $this->model->setTag('status', 'pending');

        expect($this->model->status)->toBe('pending');
    });

    it('can set tag value as property', function (): void {
        $this->model->status = 'complete';

        expect($this->model->getTagValueIn('status'))->toBe('complete');
    });

    it('returns null when no tag set', function (): void {
        expect($this->model->status)->toBeNull();
    });

    it('replaces existing value on set', function (): void {
        $this->model->status = 'complete';

        expect($this->model->status)->toBe('complete')
            ->and($this->model->tagsIn('status'))->toHaveCount(1);
    });
});

describe('Magic Attribute Access - TagDefinition Backed', function (): void {
    beforeEach(function (): void {
        // Ensure the definition tag exists
        StatusDefinition::tag();
        StatusDefinition::valueTag(StatusEnum::PENDING);
        StatusDefinition::valueTag(StatusEnum::APPROVED);

        $this->model = TestModelWithAttributes::create(['name' => 'Test']);
    });

    it('can get typed enum value as property', function (): void {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING);

        expect($this->model->priority)->toBe(StatusEnum::PENDING);
    });

    it('can set enum value as property', function (): void {
        $this->model->priority = StatusEnum::APPROVED;

        expect($this->model->getTagAs(StatusDefinition::class))->toBe(StatusEnum::APPROVED);
    });

    it('can set string value that maps to enum', function (): void {
        $this->model->priority = 'pending';

        expect($this->model->getTagAs(StatusDefinition::class))->toBe(StatusEnum::PENDING);
    });
});

describe('Magic Attributes - assigned before the model exists', function (): void {
    /*
     * A tag attribute is written to the `taggables` pivot, and a pivot row needs
     * the model's key. Mass assignment happens during fill(), before the INSERT,
     * so the key is not there yet — which is why a factory `definition()` naming
     * a tag attribute used to die on a null `taggable_id`. The assignment is
     * held and flushed on `saved` instead.
     */
    beforeEach(function (): void {
        Tag::createCategory('Status', singleSelect: true)
            ->addChildren(['pending', 'complete']);

        StatusDefinition::tag();
        StatusDefinition::valueTag(StatusEnum::PENDING);
    });

    it('writes no pivot row while the model is unsaved', function (): void {
        new TestModelWithAttributes(['name' => 'Test', 'status' => 'pending']);

        expect(DB::table('taggables')->count())->toBe(0);
    });

    it('lands the tag once the model is saved', function (): void {
        $model = new TestModelWithAttributes(['name' => 'Test', 'status' => 'pending']);
        $model->save();

        expect($model->status)->toBe('pending')
            ->and($model->tagsIn('status'))->toHaveCount(1);
    });

    it('creates the row and its pivot in a single create() call', function (): void {
        $model = TestModelWithAttributes::create(['name' => 'Test', 'status' => 'pending']);

        expect(DB::table('taggables')->where('taggable_id', $model->getKey())->count())->toBe(1);
    });

    it('holds a definition-backed attribute the same way', function (): void {
        $model = TestModelWithAttributes::create([
            'name' => 'Test',
            'priority' => StatusEnum::PENDING,
        ]);

        expect($model->priority)->toBe(StatusEnum::PENDING);
    });

    it('lands every held attribute, not just the last one', function (): void {
        $model = TestModelWithAttributes::create([
            'name' => 'Test',
            'status' => 'pending',
            'priority' => StatusEnum::PENDING,
        ]);

        expect($model->status)->toBe('pending')
            ->and($model->priority)->toBe(StatusEnum::PENDING);
    });

    it('does not replay a held assignment on the next save', function (): void {
        $model = TestModelWithAttributes::create(['name' => 'Test', 'status' => 'pending']);

        $model->setTag('status', 'complete');
        $model->name = 'Renamed';
        $model->save();

        expect($model->status)->toBe('complete');
    });

    it('still writes straight through on a model that already exists', function (): void {
        $model = TestModelWithAttributes::create(['name' => 'Test']);

        $model->status = 'complete';

        expect(DB::table('taggables')->where('taggable_id', $model->getKey())->count())->toBe(1)
            ->and($model->status)->toBe('complete');
    });
});

describe('Magic Attributes - Non-Tag Attributes', function (): void {
    it('does not interfere with regular model attributes', function (): void {
        $model = TestModelWithAttributes::create(['name' => 'Original']);

        expect($model->name)->toBe('Original');

        $model->name = 'Updated';
        expect($model->name)->toBe('Updated');
    });

    it('does not interfere with model without tagAttributes', function (): void {
        $model = TestModel::create(['name' => 'Test']);

        expect($model->name)->toBe('Test');

        // This should not try to resolve as a tag
        expect($model->nonexistent ?? 'default')->toBe('default');
    });
});
