<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Tests\Fixtures\Definitions;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\Taxon\TagDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestUser;

/**
 * The worked example of the transition contract: an enum-backed definition that
 * declares its state machine as a map and adds one code-level rule on top.
 *
 * Note what it no longer carries — `availableTransitions()` and the whole
 * map-walking body of `canTransition()` are inherited from TagDefinition now.
 */
class StatusDefinition extends TagDefinition
{
    public static string $slug = 'status';

    public static string $name = 'Status';

    public static bool $singleSelect = true;

    public static bool $global = true;

    public static function enum(): string
    {
        return StatusEnum::class;
    }

    public static function default(): StatusEnum
    {
        return StatusEnum::DRAFT;
    }

    /** @return array<string, list<StatusEnum>> */
    public static function transitions(): array
    {
        return [
            StatusEnum::DRAFT->value => [
                StatusEnum::PENDING,
            ],
            StatusEnum::PENDING->value => [
                StatusEnum::DRAFT,
                StatusEnum::APPROVED,
                StatusEnum::REJECTED,
            ],
            StatusEnum::APPROVED->value => [
                // Terminal
            ],
            StatusEnum::REJECTED->value => [
                StatusEnum::DRAFT,
            ],
        ];
    }

    public function canTransition(
        Model $model,
        string|BackedEnum|null $from,
        string|BackedEnum $to,
        mixed $user = null,
    ): bool {
        if (! parent::canTransition($model, $from, $to, $user)) {
            return false;
        }

        // Example of a rule the map cannot express: only admins can approve.
        if (static::normalizeState($to) !== StatusEnum::APPROVED->value) {
            return true;
        }

        return ! $user instanceof TestUser || $user->isAdmin();
    }
}
