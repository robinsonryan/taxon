<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Tests\Fixtures\Definitions;

use RobinsonRyan\Taxon\TagDefinition;

/**
 * A database-backed definition whose states are plain strings — the shape a
 * work-tracking app gets when its statuses live in the tags table rather than in
 * a PHP enum. It declares a transitions() map and nothing else, so every guard it
 * has comes from TagDefinition's own default canTransition().
 */
class StageDefinition extends TagDefinition
{
    public static string $slug = 'stage';

    public static string $name = 'Stage';

    public static bool $global = true;

    public static function default(): string
    {
        return 'backlog';
    }

    /**
     * The vocabulary is the map's own keys, so a state can never be written
     * that the state machine has no rules for.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_keys(static::transitions());
    }

    /** @return array<string, list<string>> */
    public static function transitions(): array
    {
        return [
            'backlog' => ['in-progress'],
            'in-progress' => ['done', 'backlog'],
            'done' => [],
        ];
    }
}
