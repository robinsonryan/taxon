<?php

declare(strict_types=1);

use RobinsonRyan\Taxon\Exceptions\InvalidTransitionException;
use RobinsonRyan\Taxon\Exceptions\UnguardedTransitionException;
use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\ClearanceDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\PriorityDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StageDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusEnum;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\WorkflowDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestUser;

describe('the transition contract lives on TagDefinition', function (): void {
    it('declares no transitions and no default until a definition says otherwise', function (): void {
        expect(PriorityDefinition::transitions())->toBeNull()
            ->and(PriorityDefinition::default())->toBeNull();
    });

    it('reports a definition with a transitions map as guarded', function (): void {
        expect(StageDefinition::guardsTransitions())->toBeTrue()
            ->and(StatusDefinition::guardsTransitions())->toBeTrue();
    });

    it('reports a definition that only overrides canTransition as guarded', function (): void {
        expect(ClearanceDefinition::transitions())->toBeNull()
            ->and(ClearanceDefinition::guardsTransitions())->toBeTrue();
    });

    it('reports a definition with neither as unguarded', function (): void {
        expect(PriorityDefinition::guardsTransitions())->toBeFalse();
    });
});

describe('transitionTo refuses to write through an unguarded definition', function (): void {
    beforeEach(function (): void {
        $this->model = TestModel::create(['name' => 'Test']);
    });

    it('throws instead of silently degrading to setTagAs', function (): void {
        $this->model->transitionTo(PriorityDefinition::class, 'high');
    })->throws(UnguardedTransitionException::class);

    it('leaves the value unwritten when it throws', function (): void {
        try {
            $this->model->transitionTo(PriorityDefinition::class, 'high');
        } catch (UnguardedTransitionException) {
            // expected
        }

        expect($this->model->getTagAs(PriorityDefinition::class))->toBeNull();
    });

    it('names the definition in the message', function (): void {
        expect(fn () => $this->model->transitionTo(PriorityDefinition::class, 'high'))
            ->toThrow(UnguardedTransitionException::class, PriorityDefinition::class);
    });

    it('still allows an unguarded write through setTagAs', function (): void {
        $this->model->setTagAs(PriorityDefinition::class, 'high');

        expect($this->model->getTagAs(PriorityDefinition::class))->toBe('high');
    });
});

describe('the default canTransition consults the transitions map', function (): void {
    beforeEach(function (): void {
        $this->model = TestModel::create(['name' => 'Test']);
    });

    it('accepts a string target, not just a BackedEnum', function (): void {
        $this->model->setTagAs(StageDefinition::class, 'backlog');

        $this->model->transitionTo(StageDefinition::class, 'in-progress');

        expect($this->model->getTagAs(StageDefinition::class))->toBe('in-progress');
    });

    it('blocks a string transition the map does not allow', function (): void {
        $this->model->setTagAs(StageDefinition::class, 'backlog');

        $this->model->transitionTo(StageDefinition::class, 'done');
    })->throws(InvalidTransitionException::class);

    it('blocks every transition out of a terminal state', function (): void {
        $this->model->setTagAs(StageDefinition::class, 'done');

        $this->model->transitionTo(StageDefinition::class, 'backlog');
    })->throws(InvalidTransitionException::class);

    it('allows the declared default as the first state', function (): void {
        $this->model->transitionTo(StageDefinition::class, 'backlog');

        expect($this->model->getTagAs(StageDefinition::class))->toBe('backlog');
    });

    it('blocks any other first state when a default is declared', function (): void {
        $this->model->transitionTo(StageDefinition::class, 'in-progress');
    })->throws(InvalidTransitionException::class);

    it('normalizes a target the same way the tag writer does', function (): void {
        $this->model->setTagAs(StageDefinition::class, 'backlog');

        $this->model->transitionTo(StageDefinition::class, 'In Progress');

        expect($this->model->getTagAs(StageDefinition::class))->toBe('in-progress');
    });
});

describe('InvalidTransitionException carries string states losslessly', function (): void {
    it('keeps a string from-state instead of nulling it', function (): void {
        $model = TestModel::create(['name' => 'Test']);
        $model->setTagAs(StageDefinition::class, 'backlog');

        try {
            $model->transitionTo(StageDefinition::class, 'done');
            $this->fail('expected an InvalidTransitionException');
        } catch (InvalidTransitionException $e) {
            expect($e->from)->toBe('backlog')
                ->and($e->to)->toBe('done')
                ->and($e->getMessage())->toContain('backlog')
                ->and($e->getMessage())->toContain('done');
        }
    });

    it('reports a null from-state as none', function (): void {
        $model = TestModel::create(['name' => 'Test']);

        try {
            $model->transitionTo(StageDefinition::class, 'done');
            $this->fail('expected an InvalidTransitionException');
        } catch (InvalidTransitionException $e) {
            expect($e->from)->toBeNull()
                ->and($e->getMessage())->toContain('none');
        }
    });
});

describe('a code-only guard is a guard', function (): void {
    beforeEach(function (): void {
        $this->model = TestModel::create(['name' => 'Test']);
        $this->user = TestUser::create(['name' => 'User', 'email' => 'user@test.com']);
        $this->admin = TestUser::create(['name' => 'Admin', 'email' => 'admin@test.com', 'is_admin' => true]);
    });

    it('allows what the overridden guard allows', function (): void {
        $this->model->transitionTo(ClearanceDefinition::class, 'public', $this->user);

        expect($this->model->getTagAs(ClearanceDefinition::class))->toBe('public');
    });

    it('blocks what the overridden guard blocks', function (): void {
        $this->model->transitionTo(ClearanceDefinition::class, 'secret', $this->user);
    })->throws(InvalidTransitionException::class);

    it('lets the guard consult the user', function (): void {
        $this->model->transitionTo(ClearanceDefinition::class, 'secret', $this->admin);

        expect($this->model->getTagAs(ClearanceDefinition::class))->toBe('secret');
    });
});

describe('availableTransitions is inherited, not hand-written', function (): void {
    beforeEach(function (): void {
        $this->model = TestModel::create(['name' => 'Test']);
    });

    it('offers only the declared default before any state is set', function (): void {
        $definition = new StageDefinition;

        expect($definition->availableTransitions($this->model))->toBe(['backlog']);
    });

    it('offers the successors of the current state', function (): void {
        $this->model->setTagAs(StageDefinition::class, 'in-progress');
        $definition = new StageDefinition;

        expect($definition->availableTransitions($this->model))->toBe(['done', 'backlog']);
    });

    it('offers nothing from a terminal state', function (): void {
        $this->model->setTagAs(StageDefinition::class, 'done');
        $definition = new StageDefinition;

        expect($definition->availableTransitions($this->model))->toBe([]);
    });

    it('offers nothing for an unguarded definition', function (): void {
        $definition = new PriorityDefinition;

        expect($definition->availableTransitions($this->model))->toBe([]);
    });
});

describe('enum-backed definitions accept either form of a state', function (): void {
    it('accepts the string form of an enum-backed target', function (): void {
        $model = TestModel::create(['name' => 'Test']);
        $model->setTagAs(StatusDefinition::class, StatusEnum::DRAFT);

        $model->transitionTo(StatusDefinition::class, 'pending');

        expect($model->getTagAs(StatusDefinition::class))->toBe(StatusEnum::PENDING);
    });
});

describe('the first state must be one the map knows', function (): void {
    beforeEach(function (): void {
        $this->model = TestModel::create(['name' => 'Test']);
    });

    it('refuses a state the map never mentions, even with no values on record', function (): void {
        expect(WorkflowDefinition::values())->toBe([]);

        $this->model->transitionTo(WorkflowDefinition::class, 'totally-bogus');
    })->throws(InvalidTransitionException::class);

    it('creates no value tag for the state it refuses', function (): void {
        try {
            $this->model->transitionTo(WorkflowDefinition::class, 'totally-bogus');
        } catch (InvalidTransitionException) {
            // expected
        }

        expect($this->model->getTagAs(WorkflowDefinition::class))->toBeNull()
            ->and(Tag::where('slug', 'totally-bogus')->exists())->toBeFalse();
    });

    it('accepts a state the map declares as a source', function (): void {
        $this->model->transitionTo(WorkflowDefinition::class, 'backlog');

        expect($this->model->getTagAs(WorkflowDefinition::class))->toBe('backlog');
    });

    it('accepts a state the map declares only as a target', function (): void {
        $this->model->transitionTo(WorkflowDefinition::class, 'archived');

        expect($this->model->getTagAs(WorkflowDefinition::class))->toBe('archived');
    });

    it('still lets a declared default be the only first state', function (): void {
        expect(fn () => $this->model->transitionTo(StageDefinition::class, 'in-progress'))
            ->toThrow(InvalidTransitionException::class);

        $this->model->transitionTo(StageDefinition::class, 'backlog');

        expect($this->model->getTagAs(StageDefinition::class))->toBe('backlog');
    });

    it('lists the states a map declares', function (): void {
        expect(WorkflowDefinition::declaredStates())
            ->toBe(['backlog', 'in-progress', 'done', 'archived'])
            ->and(PriorityDefinition::declaredStates())->toBe([]);
    });
});
