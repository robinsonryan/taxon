<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Tests\Fixtures\Definitions;

use RobinsonRyan\Taxon\TagDefinition;

/**
 * A definition whose map is keyed by human labels rather than stored values.
 *
 * The targets on the right of each arrow have always been read through
 * `normalizeState()`; the keys on the left were not, so `'In Progress'` used to
 * be a state you could enter and never leave. Both sides are normalized now, and
 * this fixture is what pins it.
 */
class ReviewDefinition extends TagDefinition
{
    public static string $slug = 'review';

    public static string $name = 'Review';

    public static bool $global = true;

    public static function default(): string
    {
        return 'Not Started';
    }

    /** @return array<string, list<string>> */
    public static function transitions(): array
    {
        return [
            'Not Started' => ['In Progress'],
            'In Progress' => ['Approved', 'Not Started'],
            'Approved' => [],
        ];
    }
}
