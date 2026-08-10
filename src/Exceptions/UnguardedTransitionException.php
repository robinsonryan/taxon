<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Exceptions;

use Exception;

/**
 * Thrown when `transitionTo()` is asked to enforce a state machine that does not
 * exist. Before 0.4.0 this case silently wrote the value like `setTagAs()`, so a
 * renamed or misspelled guard turned every check off without a sound.
 */
class UnguardedTransitionException extends Exception
{
    public function __construct(public readonly string $definition)
    {
        parent::__construct(
            "{$definition} declares no transition guard, so transitionTo() has nothing to enforce. " .
            'Declare a transitions() map or override canTransition() on the definition, ' .
            'or call setTagAs() if an unguarded write is what you want.'
        );
    }
}
