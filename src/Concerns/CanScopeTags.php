<?php

namespace RobinsonRyan\Taxon\Concerns;

trait CanScopeTags
{
    public function getScopeType(): string
    {
        return $this->getMorphClass();
    }

    public function getScopeId(): string|int
    {
        return $this->getKey();
    }
}
