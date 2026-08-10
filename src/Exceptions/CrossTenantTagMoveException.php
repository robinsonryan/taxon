<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Exceptions;

use Exception;
use RobinsonRyan\Taxon\Models\Tag;

/**
 * Thrown when a re-parent would put a tag under a parent belonging to a
 * different tenant.
 *
 * Every other write path propagates the parent's tenant to its children, so the
 * invariant "a subtree belongs to one tenant" held everywhere but here. Breaking
 * it is not a recoverable state: the subtree walks filter on `parent_id` alone,
 * so the destination tenant's `descendants()` starts returning the foreign tag,
 * and `resolvePath()` — which requires every segment to share one tenant — can no
 * longer address it from either side.
 *
 * A tenant-less tag and a tenant-less parent match; "no tenant" is its own space,
 * the way it is everywhere else in the package.
 */
class CrossTenantTagMoveException extends Exception
{
    public function __construct(
        public readonly Tag $tag,
        public readonly Tag $newParent,
        public readonly ?string $tenantId,
        public readonly ?string $parentTenantId,
    ) {
        parent::__construct(sprintf(
            "Cannot move tag '%s' (%s) under '%s' (%s): a tag and its parent must belong to the same tenant.",
            $tag->slug,
            $this->label($tenantId),
            $newParent->slug,
            $this->label($parentTenantId),
        ));
    }

    private function label(?string $tenantId): string
    {
        return $tenantId === null || $tenantId === ''
            ? 'no tenant'
            : "tenant '{$tenantId}'";
    }
}
