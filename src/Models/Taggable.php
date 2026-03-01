<?php

namespace RobinsonRyan\Taxon\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;
use RobinsonRyan\Taxon\Concerns\ConfiguresIdentifiers;

class Taggable extends MorphPivot
{
    use ConfiguresIdentifiers;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('taxon.tables.taggables', 'taggables');
    }
}
