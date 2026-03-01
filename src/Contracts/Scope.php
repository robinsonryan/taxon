<?php

namespace RobinsonRyan\Taxon\Contracts;

interface Scope
{
    public function getScopeType(): string;

    public function getScopeId(): string|int;
}
