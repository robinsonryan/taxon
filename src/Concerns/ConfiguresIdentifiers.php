<?php

namespace RobinsonRyan\Taxon\Concerns;

use Illuminate\Support\Str;

trait ConfiguresIdentifiers
{
    public function getIncrementing(): bool
    {
        return config('taxon.id_type') !== 'uuid7';
    }

    public function getKeyType(): string
    {
        return config('taxon.id_type') === 'uuid7' ? 'string' : 'int';
    }

    protected static function bootConfiguresIdentifiers(): void
    {
        if (config('taxon.id_type') === 'uuid7') {
            static::creating(function ($model) {
                if (empty($model->{$model->getKeyName()})) {
                    $model->{$model->getKeyName()} = Str::uuid7()->toString();
                }
            });
        }
    }
}
