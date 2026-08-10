<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Tests\Fixtures\Definitions;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\Taxon\TagDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestUser;

/**
 * A definition that guards transitions entirely in code: no transitions() map, an
 * overridden canTransition(). It exists to prove that overriding the guard is on
 * its own enough to satisfy transitionTo() — the map is one way to declare a
 * guard, not the only one.
 */
class ClearanceDefinition extends TagDefinition
{
    public static string $slug = 'clearance';

    public static string $name = 'Clearance';

    public static bool $global = true;

    public function canTransition(
        Model $model,
        string|BackedEnum|null $from,
        string|BackedEnum $to,
        mixed $user = null,
    ): bool {
        if (static::normalizeState($to) !== 'secret') {
            return true;
        }

        return $user instanceof TestUser && $user->isAdmin();
    }
}
