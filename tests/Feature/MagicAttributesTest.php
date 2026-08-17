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

describe('Magic Attributes - saved with events suppressed', function (): void {
    /*
     * `saveQuietly()` and `withoutEvents()` suppress the `saved` event, so a
     * flush that hangs off that event alone never runs: 0.5.0 persisted the row
     * with no pivot row and no error. Seeders and observer-avoidance code are
     * exactly where a quiet save lives, so the assignment has to survive one.
     */
    beforeEach(function (): void {
        Tag::createCategory('Status', singleSelect: true)
            ->addChildren(['pending', 'complete']);

        StatusDefinition::tag();
        StatusDefinition::valueTag(StatusEnum::PENDING);
    });

    it('lands the tag on saveQuietly()', function (): void {
        $model = new TestModelWithAttributes(['name' => 'Test', 'status' => 'pending']);
        $model->saveQuietly();

        expect(DB::table('taggables')->where('taggable_id', $model->getKey())->count())->toBe(1)
            ->and($model->getTagValueIn('status'))->toBe('pending');
    });

    it('lands the tag inside withoutEvents()', function (): void {
        $model = new TestModelWithAttributes(['name' => 'Test', 'status' => 'pending']);

        TestModelWithAttributes::withoutEvents(function () use ($model): void {
            $model->save();
        });

        expect(DB::table('taggables')->where('taggable_id', $model->getKey())->count())->toBe(1);
    });

    it('lands the tag on createQuietly()', function (): void {
        $model = TestModelWithAttributes::query()
            ->createQuietly(['name' => 'Test', 'status' => 'pending']);

        expect(DB::table('taggables')->where('taggable_id', $model->getKey())->count())->toBe(1);
    });

    it('lands a definition-backed attribute on a quiet save', function (): void {
        $model = new TestModelWithAttributes(['name' => 'Test', 'priority' => StatusEnum::PENDING]);
        $model->saveQuietly();

        expect($model->getTagAs(StatusDefinition::class))->toBe(StatusEnum::PENDING);
    });

    it('writes the pivot exactly once when events are not suppressed', function (): void {
        // The event hook and the save() backstop are both live on a loud save.
        // Flushing clears the pending list first, so the second one is a no-op.
        $model = TestModelWithAttributes::create(['name' => 'Test', 'status' => 'pending']);

        expect(DB::table('taggables')->where('taggable_id', $model->getKey())->count())->toBe(1);
    });
});

describe('Magic Attributes - read before the model is saved', function (): void {
    /*
     * A held assignment used to be invisible: getAttribute() queried the pivot,
     * found nothing (there is no key yet), and returned null — so
     * `factory()->make(['status' => 'pending'])->status` was null, as was any
     * validation between fill() and save(). A pending read now answers with the
     * value the save is going to write, spelled the way the save will spell it.
     */
    beforeEach(function (): void {
        Tag::createCategory('Status', singleSelect: true)
            ->addChildren(['pending', 'complete']);

        StatusDefinition::tag();
        StatusDefinition::valueTag(StatusEnum::PENDING);
    });

    it('reads back a string-category attribute set on an unsaved model', function (): void {
        $model = new TestModelWithAttributes(['name' => 'Test', 'status' => 'pending']);

        expect($model->status)->toBe('pending');
    });

    it('reads back a definition-backed attribute as its enum, as it will after save', function (): void {
        $model = new TestModelWithAttributes(['name' => 'Test', 'priority' => StatusEnum::PENDING]);

        expect($model->priority)->toBe(StatusEnum::PENDING);

        $model->save();

        expect($model->priority)->toBe(StatusEnum::PENDING);
    });

    it('promotes a string to the definition enum before the save, as the read will after', function (): void {
        $model = new TestModelWithAttributes(['name' => 'Test', 'priority' => 'pending']);

        expect($model->priority)->toBe(StatusEnum::PENDING);
    });

    it('spells a pending string read the way the write will spell it', function (): void {
        $model = new TestModelWithAttributes(['name' => 'Test', 'status' => 'Complete']);

        expect($model->status)->toBe('complete');

        $model->save();

        expect($model->status)->toBe('complete');
    });

    it('returns null for a tag attribute that was never assigned', function (): void {
        $model = new TestModelWithAttributes(['name' => 'Test']);

        expect($model->status)->toBeNull();
    });

    it('reads nothing back once the pending value has been flushed', function (): void {
        $model = new TestModelWithAttributes(['name' => 'Test', 'status' => 'pending']);
        $model->save();

        // Not the held copy — the pivot. Deleting it out from under the model
        // must change the answer, or the read is answering from memory.
        DB::table('taggables')->where('taggable_id', $model->getKey())->delete();

        expect($model->fresh()->status)->toBeNull();
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
