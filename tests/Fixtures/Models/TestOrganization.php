<?php

namespace RobinsonRyan\Taxon\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\Taxon\Concerns\CanScopeTags;
use RobinsonRyan\Taxon\Concerns\ConfiguresIdentifiers;
use RobinsonRyan\Taxon\Contracts\Scope;

class TestOrganization extends Model implements Scope
{
    use CanScopeTags;
    use ConfiguresIdentifiers;

    protected $guarded = [];
}
