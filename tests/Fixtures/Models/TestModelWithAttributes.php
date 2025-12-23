<?php

namespace RobinsonRyan\Taxon\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\Taxon\Concerns\ConfiguresIdentifiers;
use RobinsonRyan\Taxon\HasTags;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusDefinition;

class TestModelWithAttributes extends Model
{
    use ConfiguresIdentifiers;
    use HasTags;

    protected $table = 'test_models';

    protected $guarded = [];

    protected array $tagAttributes = [
        'status',                              // string-based category
        'priority' => StatusDefinition::class, // definition-backed
    ];
}
