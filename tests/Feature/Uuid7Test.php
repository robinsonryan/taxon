<?php

use RobinsonRyan\Taxon\Models\Tag;

describe('UUID7 Support', function (): void {
    // These assert the ConfiguresIdentifiers trait reads `taxon.id_type`; no database
    // rows are written, so no schema rebuild is involved.
    //
    // `getIncrementing()` is true under BOTH id types, and that is the point rather
    // than an oversight. In Eloquent it means "the database assigns the key on
    // insert" — true of a sequence and equally true of a `uuidv7()` column default.
    // Set it false and Eloquent compiles a plain INSERT with no `returning` clause,
    // and `create()` hands back a model with a null key.
    it('ConfiguresIdentifiers trait responds to uuid7 config', function (): void {
        config()->set('taxon.id_type', 'uuid7');

        $tag = new Tag;

        expect($tag->getIncrementing())->toBeTrue()
            ->and($tag->getKeyType())->toBe('string');
    });

    it('ConfiguresIdentifiers trait defaults to incrementing', function (): void {
        config()->set('taxon.id_type', 'incrementing');

        $tag = new Tag;

        expect($tag->getIncrementing())->toBeTrue()
            ->and($tag->getKeyType())->toBe('int');
    });
});

describe('Incrementing ID (Default)', function (): void {
    it('creates tags with incrementing IDs by default', function (): void {
        $tag = Tag::create(['name' => 'Test Tag']);

        expect($tag->id)->toBeInt();
    });
});
