<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\Uuid7TestModel;

/**
 * The shape that used to be impossible: a definition() naming a tag attribute.
 *
 * Everything here is mass-assigned into an unsaved model, so `status` is set
 * before the row exists and before it has a key — which is exactly the ordering
 * that used to take the pivot write down with a null `taggable_id`.
 *
 * @extends Factory<Uuid7TestModel>
 */
class Uuid7TestModelFactory extends Factory
{
    /** @var class-string<Uuid7TestModel> */
    protected $model = Uuid7TestModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => 'Fixture model',
            'status' => 'pending',
        ];
    }
}
