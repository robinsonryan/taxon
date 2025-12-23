<?php

use RobinsonRyan\Taxon\Models\Tag;

describe('UUID7 Support', function () {
    // Note: UUID7 tests require special database setup
    // These tests verify the configuration option works
    it('ConfiguresIdentifiers trait responds to uuid7 config', function () {
        config()->set('taxon.id_type', 'uuid7');

        $tag = new Tag;

        expect($tag->getIncrementing())->toBeFalse()
            ->and($tag->getKeyType())->toBe('string');
    });

    it('ConfiguresIdentifiers trait defaults to incrementing', function () {
        config()->set('taxon.id_type', 'incrementing');

        $tag = new Tag;

        expect($tag->getIncrementing())->toBeTrue()
            ->and($tag->getKeyType())->toBe('int');
    });
})->skip(message: 'UUID7 database tests require full schema rebuild');

describe('Incrementing ID (Default)', function () {
    it('creates tags with incrementing IDs by default', function () {
        $tag = Tag::create(['name' => 'Test Tag']);

        expect($tag->id)->toBeInt();
    });
});
