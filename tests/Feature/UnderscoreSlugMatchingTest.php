<?php

declare(strict_types=1);

use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\PipelineDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\PipelineStateEnum;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;

/**
 * A tag stored under an underscore-bearing slug — what `valueTag()` writes for an
 * enum case like `in_progress`, and what a seeded taxonomy may hold — has to be
 * findable through the read side, whichever of the three spellings the caller
 * reaches for: the stored value, its slug, or the slug re-underscored.
 *
 * The write side is deliberately not part of this: what gets stored is unchanged,
 * because enum round-tripping (`Enum::from($tag->slug)`) depends on it.
 */
describe('enum-backed underscore values', function (): void {
    beforeEach(function (): void {
        $this->model = TestModel::create(['name' => 'Test']);
        $this->model->setTagAs(PipelineDefinition::class, PipelineStateEnum::IN_PROGRESS);

        $this->other = TestModel::create(['name' => 'Other']);
        $this->other->setTagAs(PipelineDefinition::class, PipelineStateEnum::SHIPPED);
    });

    it('stores the backing value verbatim', function (): void {
        expect(PipelineDefinition::tag()->children()->pluck('slug')->all())
            ->toContain('in_progress');
    });

    it('finds the tag through hasTagAs in every spelling', function (string $spelling): void {
        expect($this->model->hasTagAs(PipelineDefinition::class, $spelling))->toBeTrue();
    })->with(['in_progress', 'in-progress', 'In Progress']);

    it('finds the tag through hasTagAs given the enum case', function (): void {
        expect($this->model->hasTagAs(PipelineDefinition::class, PipelineStateEnum::IN_PROGRESS))
            ->toBeTrue();
    });

    it('finds the tag through hasTagIn in every spelling', function (string $spelling): void {
        expect($this->model->hasTagIn('pipeline', $spelling))->toBeTrue();
    })->with(['in_progress', 'in-progress', 'In Progress']);

    it('finds the tag through hasTag in every spelling', function (string $spelling): void {
        expect($this->model->hasTag($spelling))->toBeTrue();
    })->with(['in_progress', 'in-progress', 'In Progress']);

    it('finds the model through withTagIn in every spelling', function (string $spelling): void {
        expect(TestModel::query()->withTagIn('pipeline', $spelling)->pluck('name')->all())
            ->toBe(['Test']);
    })->with(['in_progress', 'in-progress', 'In Progress']);

    it('finds the model through withAnyTagIn in every spelling', function (string $spelling): void {
        expect(TestModel::query()->withAnyTagIn('pipeline', ['not_started', $spelling])->pluck('name')->all())
            ->toBe(['Test']);
    })->with(['in_progress', 'in-progress', 'In Progress']);

    it('finds the model through withTag in every spelling', function (string $spelling): void {
        expect(TestModel::query()->withTag($spelling)->pluck('name')->all())->toBe(['Test']);
    })->with(['in_progress', 'in-progress', 'In Progress']);

    it('finds the model through withAnyTag and withAllTags in every spelling', function (string $spelling): void {
        expect(TestModel::query()->withAnyTag([$spelling])->pluck('name')->all())->toBe(['Test'])
            ->and(TestModel::query()->withAllTags([$spelling])->pluck('name')->all())->toBe(['Test']);
    })->with(['in_progress', 'in-progress', 'In Progress']);

    it('excludes the model through withoutTagIn in every spelling', function (string $spelling): void {
        expect(TestModel::query()->withoutTagIn('pipeline', $spelling)->pluck('name')->all())
            ->toBe(['Other']);
    })->with(['in_progress', 'in-progress', 'In Progress']);

    it('excludes the model through withoutTag in every spelling', function (string $spelling): void {
        expect(TestModel::query()->withoutTag($spelling)->pluck('name')->all())->toBe(['Other']);
    })->with(['in_progress', 'in-progress', 'In Progress']);

    it('answers hasAnyTagIn and hasAllTagsIn in every spelling', function (string $spelling): void {
        expect($this->model->hasAnyTagIn('pipeline', ['shipped', $spelling]))->toBeTrue()
            ->and($this->model->hasAllTagsIn('pipeline', [$spelling]))->toBeTrue();
    })->with(['in_progress', 'in-progress', 'In Progress']);

    it('answers hasAnyTag and hasAllTags in every spelling', function (string $spelling): void {
        expect($this->model->hasAnyTag([$spelling]))->toBeTrue()
            ->and($this->model->hasAllTags([$spelling]))->toBeTrue();
    })->with(['in_progress', 'in-progress', 'In Progress']);

    it('still tells the two states apart', function (): void {
        expect($this->model->hasTagIn('pipeline', 'shipped'))->toBeFalse()
            ->and($this->model->hasTagAs(PipelineDefinition::class, PipelineStateEnum::SHIPPED))->toBeFalse()
            ->and(TestModel::query()->withTagIn('pipeline', 'shipped')->pluck('name')->all())->toBe(['Other'])
            ->and(TestModel::query()->withTagIn('pipeline', 'not_started')->count())->toBe(0);
    });
});

/**
 * The same read paths on a category and a value whose stored slugs contain
 * underscores — the shape a definition takes when its own `$slug` is underscored,
 * since `TagDefinition::tag()` stores that verbatim too.
 */
describe('underscore-bearing categories', function (): void {
    beforeEach(function (): void {
        $this->category = Tag::createCategory('Work Order Status', slug: 'work_order_status');
        $onHold = $this->category->addChild('On Hold', slug: 'on_hold');
        $this->category->addChild('Complete');

        $this->model = TestModel::create(['name' => 'Test']);
        $this->model->tags()->attach($onHold->id);
        $this->model->load('tags');
    });

    it('reuses the existing category rather than minting a slugged duplicate', function (): void {
        $this->model->setTag('work-order-status', 'complete');

        expect(Tag::whereNull('parent_id')->pluck('slug')->all())->toBe(['work_order_status'])
            ->and($this->model->getTagValueIn('work_order_status'))->toBe('complete');
    });

    it('reads the value back through tagsIn in every category spelling', function (string $spelling): void {
        expect($this->model->tagsIn($spelling)->pluck('slug')->all())->toBe(['on_hold']);
    })->with(['work_order_status', 'work-order-status', 'Work Order Status']);

    it('answers hasTagIn across both halves', function (string $category, string $value): void {
        expect($this->model->hasTagIn($category, $value))->toBeTrue();
    })->with([
        ['work_order_status', 'on_hold'],
        ['work-order-status', 'on-hold'],
        ['Work Order Status', 'On Hold'],
    ]);

    it('finds the model through withTagIn in every spelling of both halves', function (string $category, string $value): void {
        expect(TestModel::query()->withTagIn($category, $value)->pluck('name')->all())->toBe(['Test']);
    })->with([
        ['work_order_status', 'on_hold'],
        ['work-order-status', 'on-hold'],
        ['Work Order Status', 'On Hold'],
        ['work-order-status', 'on_hold'],
        ['work_order_status', 'on-hold'],
    ]);

    it('removes the value through removeTag in every spelling', function (string $spelling): void {
        $this->model->removeTag('work_order_status', $spelling);

        expect($this->model->tagsIn('work_order_status'))->toBeEmpty();
    })->with(['on_hold', 'on-hold', 'On Hold']);

    it('clears the category through removeTagsIn in every spelling', function (string $spelling): void {
        $this->model->removeTagsIn($spelling);

        expect($this->model->tagsIn('work_order_status'))->toBeEmpty();
    })->with(['work_order_status', 'work-order-status', 'Work Order Status']);
});

/**
 * An underscore-bearing root tag, attached directly rather than through a
 * category — the direct tagging API's half of the same read paths.
 */
describe('underscore-bearing root tags', function (): void {
    beforeEach(function (): void {
        $this->tag = Tag::create(['name' => 'Rush Job', 'slug' => 'rush_job']);
        $this->model = TestModel::create(['name' => 'Test']);
        $this->model->tags()->attach($this->tag->id);
        $this->model->load('tags');
    });

    it('detaches the tag through untag in every spelling', function (string $spelling): void {
        $this->model->untag($spelling);

        expect($this->model->tags)->toBeEmpty();
    })->with(['rush_job', 'rush-job', 'Rush Job']);
});

/**
 * Nothing above may cost the plain-string case anything: a tag written through
 * the string API is still stored under its canonical slug, and still answers to
 * the same spellings it always did.
 */
describe('plain-string tags are unchanged', function (): void {
    beforeEach(function (): void {
        $this->model = TestModel::create(['name' => 'Test']);
    });

    it('still stores the canonical hyphenated slug for a string value', function (): void {
        $this->model->setTag('status', 'In Review');

        expect($this->model->getTagValueIn('status'))->toBe('in-review')
            ->and(Tag::where('slug', 'in-review')->exists())->toBeTrue()
            ->and(Tag::where('slug', 'in_review')->exists())->toBeFalse();
    });

    it('still stores the canonical slug when the caller writes an underscore', function (): void {
        $this->model->setTag('status', 'in_review');

        expect($this->model->getTagValueIn('status'))->toBe('in-review');
    });

    it('still stores the canonical slug for a direct tag', function (): void {
        $this->model->tag('Rush Job');

        expect($this->model->tags->pluck('slug')->all())->toBe(['rush-job']);
    });

    it('still matches a hyphen-stored tag by its usual spellings', function (string $spelling): void {
        $this->model->tag('Rush Job');

        expect($this->model->hasTag($spelling))->toBeTrue()
            ->and(TestModel::query()->withTag($spelling)->count())->toBe(1)
            ->and(TestModel::query()->withoutTag($spelling)->count())->toBe(0);
    })->with(['rush-job', 'Rush Job', 'rush_job']);

    it('still refuses a tag the model does not carry', function (): void {
        $this->model->tag('Rush Job');

        expect($this->model->hasTag('slow-job'))->toBeFalse()
            ->and($this->model->hasAllTags(['rush-job', 'slow-job']))->toBeFalse()
            ->and(TestModel::query()->withTag('slow-job')->count())->toBe(0)
            ->and(TestModel::query()->withoutTag('slow-job')->count())->toBe(1);
    });
});
