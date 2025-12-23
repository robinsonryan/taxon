<?php

namespace RobinsonRyan\Taxon\Exceptions;

use Exception;

class ImmutableTagDefinitionException extends Exception
{
    public function __construct(string $class)
    {
        parent::__construct(
            "Cannot modify values for immutable TagDefinition: {$class}"
        );
    }
}
