<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusEnum;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModelWithAttributes;
use RobinsonRyan\Taxon\Tests\Fixtures\Seeders\TaggedModelSeeder;

/*
|--------------------------------------------------------------------------
| Seeding a tagged model, end to end
|--------------------------------------------------------------------------
|
| The acceptance bar for 0.5.1: a consumer's seeder works. Every shape in
| TaggedModelSeeder assigns a tag attribute before the row has a key, and the
| quiet-save shape is the one 0.5.0 persisted with no pivot row and no error.
| Run the seeder the way `db:seed` runs it, then count pivot rows.
|
*/

beforeEach(function (): void {
    $this->seed(TaggedModelSeeder::class);
});

it('seeds every model the seeder declares', function (): void {
    expect(TestModelWithAttributes::count())->toBe(TaggedModelSeeder::FACTORY_COUNT + 3);
});

it('gives every seeded model exactly one pivot row', function (): void {
    $models = TestModelWithAttributes::all();

    foreach ($models as $model) {
        expect(DB::table('taggables')->where('taggable_id', $model->getKey())->count())
            ->toBe(1, "no pivot row for '{$model->name}'");
    }
});

it('lands the tag from a quiet save', function (): void {
    $model = TestModelWithAttributes::where('name', TaggedModelSeeder::QUIET)->sole();

    expect($model->status)->toBe('complete');
});

it('lands the tag from a plain create()', function (): void {
    $model = TestModelWithAttributes::where('name', TaggedModelSeeder::CREATED)->sole();

    expect($model->status)->toBe('pending');
});

it('lands the tag on every row a factory batch made', function (): void {
    $models = TestModelWithAttributes::where('name', 'Fixture model')->get();

    expect($models)->toHaveCount(TaggedModelSeeder::FACTORY_COUNT)
        ->and($models->every(fn ($model): bool => $model->status === 'pending'))->toBeTrue();
});

it('lands a definition-backed tag on a model made first and saved after', function (): void {
    $model = TestModelWithAttributes::where('name', TaggedModelSeeder::MADE)->sole();

    expect($model->priority)->toBe(StatusEnum::PENDING);
});

it('writes no orphan pivot rows', function (): void {
    expect(DB::table('taggables')->count())->toBe(TaggedModelSeeder::FACTORY_COUNT + 3);
});
