<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\Taxon\HasTags;
use RobinsonRyan\Taxon\Tests\Fixtures\Factories\Uuid7TestModelFactory;

/**
 * A host-application model keyed the way the doctrine says a consumer should key
 * one: `uuid` column, `uuidv7()` default, nothing generated in PHP.
 *
 * `$incrementing = true` reads oddly beside a UUID and is the whole point — in
 * Eloquent it means "the database assigns the key on insert", which is what a
 * column default does. It is what makes the INSERT compile with `returning "id"`
 * so the generated key is hydrated back.
 *
 * @property string $name
 */
class Uuid7TestModel extends Model
{
    /** @use HasFactory<Uuid7TestModelFactory> */
    use HasFactory;

    use HasTags;

    public $incrementing = true;

    protected $table = 'uuid7_test_models';

    protected $keyType = 'string';

    protected $guarded = [];

    /** @var array<int|string, string> */
    protected array $tagAttributes = ['status'];

    protected static function newFactory(): Uuid7TestModelFactory
    {
        return Uuid7TestModelFactory::new();
    }
}
