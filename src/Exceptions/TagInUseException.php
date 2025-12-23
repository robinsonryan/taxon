<?php

namespace RobinsonRyan\Taxon\Exceptions;

use Exception;
use RobinsonRyan\Taxon\Models\Tag;

class TagInUseException extends Exception
{
    public function __construct(
        public readonly Tag $tag
    ) {
        $count = $tag->taggablesCount();

        parent::__construct(
            "Cannot delete tag '{$tag->name}': currently assigned to {$count} model(s)."
        );
    }
}
