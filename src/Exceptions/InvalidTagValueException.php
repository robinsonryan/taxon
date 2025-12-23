<?php

namespace RobinsonRyan\Taxon\Exceptions;

use Exception;

class InvalidTagValueException extends Exception
{
    public function __construct(string $value, string $definition)
    {
        parent::__construct(
            "'{$value}' is not a valid value for {$definition}"
        );
    }
}
