<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Exceptions;

use Exception;

/**
 * Thrown when a write would land two tags on the same (slug, parent, tenant).
 * `tags_unique_slug_parent_tenant` would reject it anyway — this turns that into
 * a domain error the caller can act on, rather than a QueryException that also
 * aborts the surrounding PostgreSQL transaction.
 */
class DuplicateTagSlugException extends Exception
{
    public function __construct(
        public readonly string $slug,
        public readonly int|string|null $parentId = null,
        public readonly ?string $tenantId = null,
    ) {
        $where = $parentId === null ? 'at the root' : "under tag {$parentId}";
        $scope = $tenantId === null ? '' : " for tenant '{$tenantId}'";

        parent::__construct("A tag with slug '{$slug}' already exists {$where}{$scope}.");
    }
}
