<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\Taxon\Concerns\ConfiguresIdentifiers;
use RobinsonRyan\Taxon\HasTags;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Factories\TestModelWithAttributesFactory;

class TestModelWithAttributes extends Model
{
    use ConfiguresIdentifiers;

    /** @use HasFactory<TestModelWithAttributesFactory> */
    use HasFactory;

    use HasTags;

    protected $table = 'test_models';

    protected $guarded = [];

    /** @var array<int|string, string> */
    protected array $tagAttributes = [
        'status',                              // string-based category
        'priority' => StatusDefinition::class, // definition-backed
    ];

    protected static function newFactory(): TestModelWithAttributesFactory
    {
        return TestModelWithAttributesFactory::new();
    }
}
