<?php

declare(strict_types=1);

namespace RobinsonRyan\Taxon\Tests\Fixtures\Seeders;

use Illuminate\Database\Seeder;
use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusEnum;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModelWithAttributes;

/**
 * A consumer's seeder, written the four ways a consumer actually writes one.
 *
 * Seeding is where every awkward shape shows up at once: mass assignment
 * through `create()`, a quiet save to keep observers out of a bulk run, a
 * factory whose `definition()` names a tag attribute, and a model built with
 * `make()` and persisted later. Each of them assigns the tag before the row has
 * a key, and each has to end with a pivot row.
 */
class TaggedModelSeeder extends Seeder
{
    public const CREATED = 'Created loudly';

    public const QUIET = 'Saved quietly';

    public const MADE = 'Made, then saved';

    public const FACTORY_COUNT = 3;

    public function run(): void
    {
        Tag::createCategory('Status', singleSelect: true)
            ->addChildren(['pending', 'complete']);

        StatusDefinition::tag();
        StatusDefinition::valueTag(StatusEnum::PENDING);

        TestModelWithAttributes::create([
            'name' => self::CREATED,
            'status' => 'pending',
        ]);

        // The one 0.5.0 dropped on the floor: no events, so no `saved` hook.
        $quiet = new TestModelWithAttributes([
            'name' => self::QUIET,
            'status' => 'complete',
        ]);
        $quiet->saveQuietly();

        TestModelWithAttributes::factory()
            ->count(self::FACTORY_COUNT)
            ->create(['status' => 'pending']);

        $made = TestModelWithAttributes::factory()->make([
            'name' => self::MADE,
            'priority' => StatusEnum::PENDING,
        ]);
        $made->save();
    }
}
