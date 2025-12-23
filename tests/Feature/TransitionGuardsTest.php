<?php

use RobinsonRyan\Taxon\Exceptions\InvalidTransitionException;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusEnum;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestUser;

describe('Transition Guards', function () {
    beforeEach(function () {
        $this->model = TestModel::create(['name' => 'Test']);
        $this->user = TestUser::create(['name' => 'User', 'email' => 'user@test.com']);
        $this->admin = TestUser::create(['name' => 'Admin', 'email' => 'admin@test.com', 'is_admin' => true]);
    });

    it('allows valid transitions', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::DRAFT);

        $this->model->transitionTo(StatusDefinition::class, StatusEnum::PENDING, $this->user);

        expect($this->model->getTagAs(StatusDefinition::class))->toBe(StatusEnum::PENDING);
    });

    it('blocks invalid transitions', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::DRAFT);

        $this->model->transitionTo(StatusDefinition::class, StatusEnum::APPROVED, $this->user);
    })->throws(InvalidTransitionException::class);

    it('respects permission guards', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING);

        // Regular user cannot approve
        $this->model->transitionTo(StatusDefinition::class, StatusEnum::APPROVED, $this->user);
    })->throws(InvalidTransitionException::class);

    it('allows admins through permission guards', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING);

        $this->model->transitionTo(StatusDefinition::class, StatusEnum::APPROVED, $this->admin);

        expect($this->model->getTagAs(StatusDefinition::class))->toBe(StatusEnum::APPROVED);
    });

    it('returns available transitions for current state', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING);
        $definition = new StatusDefinition;

        $available = $definition->availableTransitions($this->model, $this->user);

        expect($available)->toContain(StatusEnum::DRAFT, StatusEnum::REJECTED)
            ->and($available)->not->toContain(StatusEnum::APPROVED);
    });

    it('returns admin-only transitions for admins', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING);
        $definition = new StatusDefinition;

        $available = $definition->availableTransitions($this->model, $this->admin);

        expect($available)->toContain(StatusEnum::APPROVED);
    });

    it('blocks transitions from terminal states', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::APPROVED);

        $this->model->transitionTo(StatusDefinition::class, StatusEnum::PENDING, $this->admin);
    })->throws(InvalidTransitionException::class);
});
