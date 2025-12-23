<?php

namespace RobinsonRyan\Taxon\Tests\Fixtures\Definitions;

enum StatusEnum: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
