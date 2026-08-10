<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Tests\Fixtures\Definitions;

use RobinsonRyan\Taxon\TagDefinition;

/**
 * A database-backed definition that declares a transitions() map and nothing
 * else — no `default()`, no `values()` override. It is the shape that used to
 * leave the initial-state hole open: `values()` reads the category's children,
 * which is empty until something is written, so the old guard let *any* string
 * be the first state and wedged the model in a state the map has no rules for.
 *
 * `archived` appears only on the right-hand side of an arrow, so it also pins
 * that the map's vocabulary is its keys **and** its targets.
 */
class WorkflowDefinition extends TagDefinition
{
    public static string $slug = 'workflow';

    public static string $name = 'Workflow';

    public static bool $global = true;

    /** @return array<string, list<string>> */
    public static function transitions(): array
    {
        return [
            'backlog' => ['in-progress'],
            'in-progress' => ['done', 'backlog'],
            'done' => ['archived'],
        ];
    }
}
