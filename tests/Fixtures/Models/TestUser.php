<?php

namespace RobinsonRyan\Taxon\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\Taxon\Concerns\ConfiguresIdentifiers;
use RobinsonRyan\Taxon\HasTags;

class TestUser extends Model
{
    use ConfiguresIdentifiers;
    use HasTags;

    protected $guarded = [];

    public function isAdmin(): bool
    {
        return $this->is_admin;
    }
}
