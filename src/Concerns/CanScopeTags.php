<?php

namespace RobinsonRyan\Taxon\Concerns;

use RobinsonRyan\Taxon\Contracts\Scope;

/**
 * Implements the Scope contract for Eloquent models.
 *
 * The consuming class must also declare `implements Scope` on its class definition:
 *
 *     class Organization extends Model implements Scope
 *     {
 *         use CanScopeTags;
 *     }
 *
 * @see Scope
 */
trait CanScopeTags
{
    public function initializeCanScopeTags(): void
    {
        if (! $this instanceof Scope) {
            throw new \LogicException(
                static::class . ' uses CanScopeTags but does not implement ' . Scope::class
            );
        }
    }

    public function getScopeType(): string
    {
        return $this->getMorphClass();
    }

    public function getScopeId(): string|int
    {
        return $this->getKey();
    }
}
