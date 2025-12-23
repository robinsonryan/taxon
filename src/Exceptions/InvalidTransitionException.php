<?php

namespace RobinsonRyan\Taxon\Exceptions;

use BackedEnum;
use Exception;
use Illuminate\Database\Eloquent\Model;

class InvalidTransitionException extends Exception
{
    public function __construct(
        public readonly Model $model,
        public readonly ?BackedEnum $from,
        public readonly BackedEnum $to,
    ) {
        $fromLabel = $from?->value ?? 'none';

        parent::__construct(
            "Cannot transition from '{$fromLabel}' to '{$to->value}'."
        );
    }
}
