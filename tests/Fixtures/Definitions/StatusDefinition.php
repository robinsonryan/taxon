<?php

namespace RobinsonRyan\Taxon\Tests\Fixtures\Definitions;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\Taxon\TagDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestUser;

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

    public function canTransition(Model $model, ?StatusEnum $from, StatusEnum $to, ?TestUser $user = null): bool
    {
        if (! $from instanceof StatusEnum) {
            return $to === static::default();
        }

        $allowed = static::transitions()[$from->value] ?? [];

        if (! in_array($to, $allowed)) {
            return false;
        }

        // Example: only admins can approve
        return ! ($to === StatusEnum::APPROVED && $user && ! $user->isAdmin());
    }

    /** @return array<int, StatusEnum> */
    public function availableTransitions(TestModel $model, ?TestUser $user = null): array
    {
        $current = $model->getTagAs(static::class);

        // getTagAs() returns the raw slug for database-backed definitions; this one is
        // enum-backed, so anything that is not a StatusEnum means "no state set yet".
        if (! $current instanceof StatusEnum) {
            return [static::default()];
        }

        $possible = static::transitions()[$current->value] ?? [];

        return array_filter(
            $possible,
            fn (StatusEnum $status): bool => $this->canTransition($model, $current, $status, $user)
        );
    }
}
