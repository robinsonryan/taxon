<?php

use RobinsonRyan\Taxon\Exceptions\ImmutableTagDefinitionException;
use RobinsonRyan\Taxon\Exceptions\InvalidTagValueException;
use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\PriorityDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusEnum;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;

describe('TagDefinition - Enum Backed', function (): void {
    it('gets the category tag, creating if needed', function (): void {
        $tag = StatusDefinition::tag();

        expect($tag)->toBeInstanceOf(Tag::class)
            ->and($tag->slug)->toBe('status')
            ->and($tag->assignable)->toBeFalse();
    });

    it('returns values from enum', function (): void {
        $values = StatusDefinition::values();

        expect($values)->toContain('draft', 'pending', 'approved', 'rejected');
    });

    it('reports as immutable', function (): void {
        expect(StatusDefinition::valuesMutable())->toBeFalse();
    });

    it('creates value tags from enum', function (): void {
        $tag = StatusDefinition::valueTag(StatusEnum::PENDING);

        expect($tag->slug)->toBe('pending')
            ->and($tag->parent_id)->toBe(StatusDefinition::tag()->id);
    });

    it('validates enum values', function (): void {
        expect(StatusDefinition::isValidValue('pending'))->toBeTrue()
            ->and(StatusDefinition::isValidValue('invalid'))->toBeFalse();
    });
});

describe('TagDefinition - Database Backed', function (): void {
    it('reports as mutable', function (): void {
        expect(PriorityDefinition::valuesMutable())->toBeTrue();
    });

    it('returns values from database', function (): void {
        PriorityDefinition::addValue('High');
        PriorityDefinition::addValue('Low');

        $values = PriorityDefinition::values();

        expect($values)->toContain('high', 'low');
    });

    it('can add values', function (): void {
        $tag = PriorityDefinition::addValue('Critical', 'critical');

        expect($tag->slug)->toBe('critical')
            ->and($tag->name)->toBe('Critical');
    });

    it('can remove values', function (): void {
        PriorityDefinition::addValue('Temporary');
        expect(PriorityDefinition::values())->toContain('temporary');

        PriorityDefinition::removeValue('temporary');
        expect(PriorityDefinition::values())->not->toContain('temporary');
    });

    it('throws when adding to immutable definition', function (): void {
        StatusDefinition::addValue('New Status');
    })->throws(ImmutableTagDefinitionException::class);
});

describe('TagDefinition - Model Integration', function (): void {
    beforeEach(function (): void {
        $this->model = TestModel::create(['name' => 'Test']);
    });

    it('can set tag using definition class', function (): void {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING);

        expect($this->model->getTagValueIn('status'))->toBe('pending');
    });

    it('can set tag using enum directly', function (): void {
        $this->model->setTagAs(StatusDefinition::class, 'approved');

        expect($this->model->getTagValueIn('status'))->toBe('approved');
    });

    it('validates against definition values', function (): void {
        $this->model->setTagAs(StatusDefinition::class, 'invalid');
    })->throws(InvalidTagValueException::class);

    it('gets typed value via definition', function (): void {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::APPROVED);

        $value = $this->model->getTagAs(StatusDefinition::class);

        expect($value)->toBe(StatusEnum::APPROVED);
    });
});
