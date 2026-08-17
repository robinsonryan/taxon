<?php

namespace RobinsonRyan\Taxon\Concerns;

/**
 * Configures primary key settings for this package's models, per `taxon.id_type`.
 *
 * In uuid7 mode the database (PostgreSQL 18+) generates UUID7 values via its
 * native uuidv7() function as the column default — Laravel never generates them.
 *
 * `$keyType = 'string'` tells Eloquent the key is a UUID rather than an integer.
 * `$incrementing = true` does NOT mean "auto-increment"; in Eloquent it means
 * "the database assigns the key on insert", which is exactly what a uuidv7()
 * column default does. It makes Eloquent use `insertGetId()`, so the INSERT is
 * compiled with PostgreSQL's `returning "id"` clause and the generated UUID is
 * hydrated back onto the model. Without it the model would come back from
 * `create()` with a null key.
 *
 * That last sentence is why both branches set it. Taggable is a MorphPivot, and
 * Pivot ships `$incrementing = false`, so the bigint branch has to say so too or
 * a pivot row saved through the model comes back keyless.
 */
trait ConfiguresIdentifiers
{
    public function initializeConfiguresIdentifiers(): void
    {
        $this->incrementing = true;
        $this->keyType = config('taxon.id_type') === 'uuid7' ? 'string' : 'int';
    }
}
