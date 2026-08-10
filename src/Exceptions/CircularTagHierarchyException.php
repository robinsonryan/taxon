<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Exceptions;

use Exception;
use RobinsonRyan\Taxon\Models\Tag;

/**
 * Thrown when a re-parent would put a tag inside its own subtree. The database
 * cannot catch this — a cycle in an adjacency list is a perfectly valid set of
 * rows — and once one exists, every ancestor walk over it runs forever.
 */
class CircularTagHierarchyException extends Exception
{
    public function __construct(
        public readonly Tag $tag,
        public readonly Tag $newParent,
    ) {
        $target = $newParent->is($tag) ? 'itself' : "'{$newParent->slug}'";

        parent::__construct(
            "Cannot move tag '{$tag->slug}' under {$target}: that would put it inside its own subtree."
        );
    }
}
