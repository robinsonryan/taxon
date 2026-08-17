<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModelWithAttributes;

/**
 * A bigint-keyed factory whose definition() names a tag attribute.
 *
 * The uuid7 twin of this lives in Uuid7TestModelFactory. Both matter: the key
 * is null during fill() whichever end generates it, so the seeding shapes have
 * to hold on the shipped `incrementing` default too.
 *
 * @extends Factory<TestModelWithAttributes>
 */
class TestModelWithAttributesFactory extends Factory
{
    /** @var class-string<TestModelWithAttributes> */
    protected $model = TestModelWithAttributes::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Fixture model',
            'status' => 'pending',
        ];
    }
}
