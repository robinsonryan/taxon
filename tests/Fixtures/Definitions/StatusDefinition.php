<?php

namespace RobinsonRyan\Taxon\Tests\Fixtures\Definitions;

use RobinsonRyan\Taxon\TagDefinition;

enum StatusEnum: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}

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
}
