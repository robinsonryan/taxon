<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Tests\Fixtures\Definitions;

use RobinsonRyan\Taxon\TagDefinition;

/**
 * An enum-backed definition whose backing values contain underscores, so the map
 * is keyed by strings that slugging would change. The guard has to match the
 * stored value exactly before it tries a normalized comparison.
 */
class PipelineDefinition extends TagDefinition
{
    public static string $slug = 'pipeline';

    public static string $name = 'Pipeline';

    public static bool $global = true;

    public static function enum(): string
    {
        return PipelineStateEnum::class;
    }

    public static function default(): PipelineStateEnum
    {
        return PipelineStateEnum::NOT_STARTED;
    }

    /** @return array<string, list<PipelineStateEnum>> */
    public static function transitions(): array
    {
        return [
            PipelineStateEnum::NOT_STARTED->value => [PipelineStateEnum::IN_PROGRESS],
            PipelineStateEnum::IN_PROGRESS->value => [PipelineStateEnum::SHIPPED],
            PipelineStateEnum::SHIPPED->value => [],
        ];
    }
}
