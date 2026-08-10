<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Exceptions;

use Exception;
use RobinsonRyan\Taxon\Models\Tag;

/**
 * Thrown when a tree walk would follow more than `taxon.max_tree_depth` edges.
 *
 * A recursive CTE over an adjacency list has no natural stopping point: a cycle
 * in `parent_id` is a perfectly valid set of rows, and the walk over it runs
 * until something kills the statement — holding a connection and a worker while
 * it does, because killing the *client* does not stop the query. The walks
 * therefore probe one level past the limit and raise this instead, so corrupt
 * data degrades to an error a caller can see rather than a hung request.
 *
 * It means one of two things: `parent_id` contains a cycle (`moveTo()` refuses to
 * create one, so this is data written around it), or the tree is genuinely
 * deeper than the configured limit — in which case raise `taxon.max_tree_depth`.
 */
class TagDepthExceededException extends Exception
{
    public function __construct(
        public readonly Tag $tag,
        public readonly int $limit,
    ) {
        parent::__construct(sprintf(
            "Walking the tag tree from '%s' passed the %d-level limit. Either parent_id holds a cycle, " .
            'or the tree is deeper than taxon.max_tree_depth allows.',
            $tag->slug,
            $limit,
        ));
    }
}
