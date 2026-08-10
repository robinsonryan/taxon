<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Exceptions;

use BackedEnum;
use Exception;
use Illuminate\Database\Eloquent\Model;

class InvalidTransitionException extends Exception
{
    public function __construct(
        public readonly Model $model,
        public readonly string|BackedEnum|null $from,
        public readonly string|BackedEnum $to,
    ) {
        parent::__construct(sprintf(
            "Cannot transition from '%s' to '%s'.",
            $from === null ? 'none' : $this->label($from),
            $this->label($to),
        ));
    }

    private function label(string|BackedEnum $state): string
    {
        return $state instanceof BackedEnum ? (string) $state->value : $state;
    }
}
