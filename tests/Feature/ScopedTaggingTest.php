<?php

use Illuminate\Support\Facades\DB;
use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusEnum;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestOrganization;

beforeEach(function () {
    $this->orgA = TestOrganization::create(['name' => 'Org A']);
    $this->orgB = TestOrganization::create(['name' => 'Org B']);

    $this->status = Tag::createCategory('Status', singleSelect: true);
    $this->status->addChildren(['pending', 'complete', 'archived']);

    $this->roles = Tag::createCategory('Roles', singleSelect: false);
    $this->roles->addChildren(['admin', 'editor', 'viewer']);

    $this->model = TestModel::create(['name' => 'Test']);
});

describe('Scoped setTag', function () {
    it('sets a tag within a scope', function () {
        $this->model->setTag('status', 'pending', $this->orgA);

        expect($this->model->getTagIn('status', $this->orgA))->not->toBeNull()
            ->and($this->model->getTagIn('status', $this->orgA)->slug)->toBe('pending');
    });

    it('keeps tags independent across scopes', function () {
        $this->model->setTag('status', 'pending', $this->orgA);
        $this->model->setTag('status', 'complete', $this->orgB);

        expect($this->model->getTagValueIn('status', $this->orgA))->toBe('pending')
            ->and($this->model->getTagValueIn('status', $this->orgB))->toBe('complete');
    });

    it('replaces tag only in the targeted scope', function () {
        $this->model->setTag('status', 'pending', $this->orgA);
        $this->model->setTag('status', 'complete', $this->orgB);

        $this->model->setTag('status', 'archived', $this->orgA);

        expect($this->model->getTagValueIn('status', $this->orgA))->toBe('archived')
            ->and($this->model->getTagValueIn('status', $this->orgB))->toBe('complete');
    });

    it('scoped tags do not appear in unscoped queries', function () {
        $this->model->setTag('status', 'pending', $this->orgA);

        expect($this->model->getTagIn('status'))->toBeNull();
    });

    it('unscoped tags do not appear in scoped queries', function () {
        $this->model->setTag('status', 'pending');

        expect($this->model->getTagIn('status', $this->orgA))->toBeNull();
    });
});

describe('Scoped addTag', function () {
    it('adds tag in a specific scope', function () {
        $this->model->addTag('roles', 'admin', $this->orgA);

        expect($this->model->tagsIn('roles', $this->orgA))->toHaveCount(1)
            ->and($this->model->tagsIn('roles', $this->orgA)->first()->slug)->toBe('admin');
    });

    it('accumulates tags within a scope', function () {
        $this->model->addTag('roles', 'admin', $this->orgA);
        $this->model->addTag('roles', 'editor', $this->orgA);

        expect($this->model->tagsIn('roles', $this->orgA))->toHaveCount(2);
    });

    it('does not duplicate tags within the same scope', function () {
        $this->model->addTag('roles', 'admin', $this->orgA);
        $this->model->addTag('roles', 'admin', $this->orgA);

        expect($this->model->tagsIn('roles', $this->orgA))->toHaveCount(1);
    });

    it('allows the same tag in different scopes', function () {
        $this->model->addTag('roles', 'admin', $this->orgA);
        $this->model->addTag('roles', 'admin', $this->orgB);

        expect($this->model->tagsIn('roles', $this->orgA))->toHaveCount(1)
            ->and($this->model->tagsIn('roles', $this->orgB))->toHaveCount(1);
    });

    it('can add multiple tags at once with scope', function () {
        $this->model->addTags('roles', ['admin', 'editor'], $this->orgA);

        expect($this->model->tagsIn('roles', $this->orgA))->toHaveCount(2);
    });
});

describe('Scoped removeTag', function () {
    it('removes a tag only from the targeted scope', function () {
        $this->model->addTag('roles', 'admin', $this->orgA);
        $this->model->addTag('roles', 'admin', $this->orgB);

        $this->model->removeTag('roles', 'admin', $this->orgA);

        expect($this->model->tagsIn('roles', $this->orgA))->toHaveCount(0)
            ->and($this->model->tagsIn('roles', $this->orgB))->toHaveCount(1);
    });

    it('removes all tags in category only from the targeted scope', function () {
        $this->model->addTags('roles', ['admin', 'editor'], $this->orgA);
        $this->model->addTags('roles', ['admin', 'viewer'], $this->orgB);

        $this->model->removeTagsIn('roles', $this->orgA);

        expect($this->model->tagsIn('roles', $this->orgA))->toHaveCount(0)
            ->and($this->model->tagsIn('roles', $this->orgB))->toHaveCount(2);
    });
});

describe('Scoped hasTagIn checks', function () {
    beforeEach(function () {
        $this->model->addTag('roles', 'admin', $this->orgA);
        $this->model->addTags('roles', ['editor', 'viewer'], $this->orgB);
    });

    it('checks if model has tag in scoped category', function () {
        expect($this->model->hasTagIn('roles', 'admin', $this->orgA))->toBeTrue()
            ->and($this->model->hasTagIn('roles', 'admin', $this->orgB))->toBeFalse();
    });

    it('checks hasAnyTagIn with scope', function () {
        expect($this->model->hasAnyTagIn('roles', ['admin', 'editor'], $this->orgA))->toBeTrue()
            ->and($this->model->hasAnyTagIn('roles', ['viewer', 'editor'], $this->orgA))->toBeFalse();
    });

    it('checks hasAllTagsIn with scope', function () {
        expect($this->model->hasAllTagsIn('roles', ['editor', 'viewer'], $this->orgB))->toBeTrue()
            ->and($this->model->hasAllTagsIn('roles', ['admin', 'editor'], $this->orgB))->toBeFalse();
    });
});

describe('Scoped query scopes', function () {
    beforeEach(function () {
        $this->m1 = TestModel::create(['name' => 'M1']);
        $this->m2 = TestModel::create(['name' => 'M2']);
        $this->m3 = TestModel::create(['name' => 'M3']);

        $this->m1->setTag('status', 'pending', $this->orgA);
        $this->m2->setTag('status', 'complete', $this->orgA);
        $this->m2->setTag('status', 'pending', $this->orgB);
        $this->m3->setTag('status', 'pending');
    });

    it('scopes withTagIn by scope', function () {
        $models = TestModel::withTagIn('status', 'pending', $this->orgA)->get();

        expect($models)->toHaveCount(1)
            ->and($models->first()->name)->toBe('M1');
    });

    it('scopes withAnyTagIn by scope', function () {
        $models = TestModel::withAnyTagIn('status', ['pending', 'complete'], $this->orgA)->get();

        expect($models)->toHaveCount(2)
            ->and($models->pluck('name')->toArray())->toContain('M1', 'M2');
    });

    it('scopes withoutTagIn by scope', function () {
        // M2 and M3 don't have 'pending' in orgA, plus the base $this->model
        $models = TestModel::withoutTagIn('status', 'pending', $this->orgA)->get();

        expect($models->pluck('name')->toArray())->not->toContain('M1');
    });
});

describe('Scoped TagDefinition methods', function () {
    beforeEach(function () {
        $this->model = TestModel::create(['name' => 'Def Test']);
    });

    it('sets tag via definition with scope', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING, $this->orgA);

        expect($this->model->getTagAs(StatusDefinition::class, $this->orgA))->toBe(StatusEnum::PENDING)
            ->and($this->model->getTagAs(StatusDefinition::class))->toBeNull();
    });

    it('keeps definition tags independent across scopes', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING, $this->orgA);
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::APPROVED, $this->orgB);

        expect($this->model->getTagAs(StatusDefinition::class, $this->orgA))->toBe(StatusEnum::PENDING)
            ->and($this->model->getTagAs(StatusDefinition::class, $this->orgB))->toBe(StatusEnum::APPROVED);
    });

    it('replaces definition tag only in targeted scope', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING, $this->orgA);
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::APPROVED, $this->orgB);

        $this->model->setTagAs(StatusDefinition::class, StatusEnum::REJECTED, $this->orgA);

        expect($this->model->getTagAs(StatusDefinition::class, $this->orgA))->toBe(StatusEnum::REJECTED)
            ->and($this->model->getTagAs(StatusDefinition::class, $this->orgB))->toBe(StatusEnum::APPROVED);
    });

    it('checks hasTagAs with scope', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING, $this->orgA);

        expect($this->model->hasTagAs(StatusDefinition::class, StatusEnum::PENDING, $this->orgA))->toBeTrue()
            ->and($this->model->hasTagAs(StatusDefinition::class, StatusEnum::PENDING, $this->orgB))->toBeFalse()
            ->and($this->model->hasTagAs(StatusDefinition::class, StatusEnum::PENDING))->toBeFalse();
    });

    it('adds tag via definition with scope without duplicating', function () {
        $this->model->addTagAs(StatusDefinition::class, StatusEnum::PENDING, $this->orgA);
        $this->model->addTagAs(StatusDefinition::class, StatusEnum::PENDING, $this->orgA);

        $pivotTable = config('taxon.tables.taggables', 'taggables');
        $count = DB::table($pivotTable)
            ->where('taggable_type', $this->model->getMorphClass())
            ->where('taggable_id', $this->model->getKey())
            ->where('scope_type', $this->orgA->getMorphClass())
            ->where('scope_id', $this->orgA->getKey())
            ->count();

        expect($count)->toBe(1);
    });
});

describe('CanScopeTags trait', function () {
    it('returns morph class as scope type', function () {
        expect($this->orgA->getScopeType())->toBe($this->orgA->getMorphClass());
    });

    it('returns model key as scope id', function () {
        expect($this->orgA->getScopeId())->toBe($this->orgA->getKey());
    });
});
