<?php

namespace RobinsonRyan\Taxon\Tests\Fixtures\Definitions;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\Taxon\TagDefinition;

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

    public function canTransition(Model $model, ?StatusEnum $from, StatusEnum $to, $user = null): bool
    {
        if ($from === null) {
            return $to === static::default();
        }

        $allowed = static::transitions()[$from->value] ?? [];

        if (! in_array($to, $allowed)) {
            return false;
        }

        // Example: only admins can approve
        if ($to === StatusEnum::APPROVED && $user && ! $user->isAdmin()) {
            return false;
        }

        return true;
    }

    public function availableTransitions(Model $model, $user = null): array
    {
        $current = $model->getTagAs(static::class);

        if ($current === null) {
            return [static::default()];
        }

        $possible = static::transitions()[$current->value] ?? [];

        return array_filter(
            $possible,
            fn (StatusEnum $status) => $this->canTransition($model, $current, $status, $user)
        );
    }
}
