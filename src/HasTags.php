<?php

namespace RobinsonRyan\Taxon;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RobinsonRyan\Taxon\Models\Tag;

trait HasTags
{
    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function tags(): MorphToMany
    {
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        return $this->morphToMany(
            config('taxon.tag_model', Tag::class),
            'taggable',
            $pivotTable
        )->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Direct Tagging Methods
    |--------------------------------------------------------------------------
    */

    public function tag(string|array $tags): static
    {
        $tags = Arr::wrap($tags);
        $tagModels = $this->resolveOrCreateTags($tags);

        $this->tags()->syncWithoutDetaching($tagModels->pluck('id'));
        $this->load('tags');

        return $this;
    }

    public function untag(string|array $tags): static
    {
        $tags = Arr::wrap($tags);
        $tagIds = $this->resolveTags($tags)->pluck('id');

        $this->tags()->detach($tagIds);
        $this->load('tags');

        return $this;
    }

    public function retag(array $tags): static
    {
        $tagModels = $this->resolveOrCreateTags($tags);

        $this->tags()->sync($tagModels->pluck('id'));
        $this->load('tags');

        return $this;
    }

    public function detachAllTags(): static
    {
        $this->tags()->detach();
        $this->load('tags');

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Direct Tag Checks
    |--------------------------------------------------------------------------
    */

    public function hasTag(string $tag): bool
    {
        return $this->tags->contains(function (Tag $t) use ($tag) {
            return $t->slug === Str::slug($tag);
        });
    }

    public function hasAnyTag(array $tags): bool
    {
        $slugs = collect($tags)->map(fn ($t) => Str::slug($t));

        return $this->tags->contains(function (Tag $t) use ($slugs) {
            return $slugs->contains($t->slug);
        });
    }

    public function hasAllTags(array $tags): bool
    {
        $slugs = collect($tags)->map(fn ($t) => Str::slug($t));
        $modelSlugs = $this->tags->pluck('slug');

        return $slugs->every(fn ($slug) => $modelSlugs->contains($slug));
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeWithTag(Builder $query, string $tag): Builder
    {
        $slug = Str::slug($tag);

        return $query->whereHas('tags', function (Builder $q) use ($slug) {
            $q->where('slug', $slug);
        });
    }

    public function scopeWithAnyTag(Builder $query, array $tags): Builder
    {
        $slugs = collect($tags)->map(fn ($t) => Str::slug($t));

        return $query->whereHas('tags', function (Builder $q) use ($slugs) {
            $q->whereIn('slug', $slugs);
        });
    }

    public function scopeWithAllTags(Builder $query, array $tags): Builder
    {
        $slugs = collect($tags)->map(fn ($t) => Str::slug($t));

        foreach ($slugs as $slug) {
            $query->whereHas('tags', function (Builder $q) use ($slug) {
                $q->where('slug', $slug);
            });
        }

        return $query;
    }

    public function scopeWithoutTag(Builder $query, string $tag): Builder
    {
        $slug = Str::slug($tag);

        return $query->whereDoesntHave('tags', function (Builder $q) use ($slug) {
            $q->where('slug', $slug);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Resolution Helpers
    |--------------------------------------------------------------------------
    */

    protected function resolveOrCreateTags(array $tags): Collection
    {
        return collect($tags)->map(function ($tag) {
            $slug = Str::slug($tag);

            return Tag::firstOrCreate(
                ['slug' => $slug, 'parent_id' => null],
                ['name' => $tag]
            );
        });
    }

    protected function resolveTags(array $tags): Collection
    {
        $slugs = collect($tags)->map(fn ($t) => Str::slug($t));

        return Tag::whereIn('slug', $slugs)->whereNull('parent_id')->get();
    }
}
