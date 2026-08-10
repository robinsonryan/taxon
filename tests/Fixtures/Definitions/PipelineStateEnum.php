<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Tests\Fixtures\Definitions;

/**
 * Backing values that do **not** survive slugging: `Str::slug('in_progress')`
 * is `'in-progress'`, a different string. An enum's backing value is what the
 * state is stored under, so the map has to keep matching it exactly — this enum
 * exists to prove that normalizing the map's keys did not break that.
 */
enum PipelineStateEnum: string
{
    case NOT_STARTED = 'not_started';

    case IN_PROGRESS = 'in_progress';

    case SHIPPED = 'shipped';
}
