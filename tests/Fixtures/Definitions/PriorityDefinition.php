<?php

namespace RobinsonRyan\Taxon\Tests\Fixtures\Definitions;

use RobinsonRyan\Taxon\TagDefinition;

class PriorityDefinition extends TagDefinition
{
    public static string $slug = 'priority';

    public static string $name = 'Priority';

    public static bool $singleSelect = true;

    public static bool $global = true;

    // Database-backed: no enum() or values() override
}
