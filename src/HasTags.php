<?php

namespace RobinsonRyan\Taxon;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RobinsonRyan\Taxon\Contracts\Scope;
use RobinsonRyan\Taxon\Exceptions\InvalidTagValueException;
use RobinsonRyan\Taxon\Exceptions\InvalidTransitionException;
use RobinsonRyan\Taxon\Exceptions\TagNotFoundException;
use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Models\Taggable;

trait HasTags
{
    /*
    |--------------------------------------------------------------------------
    | Magic Attribute Access
    |--------------------------------------------------------------------------
    */

    public function getAttribute($key)
    {
        // Check if this key is a declared tag attribute
        if ($this->isTagAttribute($key)) {
            return $this->getTagAttributeValue($key);
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        // Check if this key is a declared tag attribute
        if ($this->isTagAttribute($key)) {
            $this->setTagAttributeValue($key, $value);

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    protected function isTagAttribute(string $key): bool
    {
        if (! property_exists($this, 'tagAttributes')) {
            return false;
        }

        // Supports both indexed array ['status'] and associative ['status' => Definition::class]
        return array_key_exists($key, $this->tagAttributes)
            || in_array($key, $this->tagAttributes, true);
    }

    protected function getTagAttributeValue(string $key): mixed
    {
        $definition = $this->getTagAttributeDefinition($key);

        if ($definition !== null) {
            return $this->getTagAs($definition);
        }

        return $this->getTagValueIn($key);
    }

    protected function setTagAttributeValue(string $key, mixed $value): void
    {
        $definition = $this->getTagAttributeDefinition($key);

        if ($definition !== null) {
            $this->setTagAs($definition, $value);

            return;
        }

        $this->setTag($key, $value);
    }

    /** @return class-string<TagDefinition>|null */
    protected function getTagAttributeDefinition(string $key): ?string
    {
        if (! property_exists($this, 'tagAttributes')) {
            return null;
        }

        // If associative with class value
        if (array_key_exists($key, $this->tagAttributes)) {
            $value = $this->tagAttributes[$key];
            if (is_string($value) && class_exists($value) && is_subclass_of($value, TagDefinition::class)) {
                return $value;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /** @return MorphToMany<Tag, $this> */
    public function tags(): MorphToMany
    {
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        /** @var class-string<Tag> $tagModel */
        $tagModel = config('taxon.tag_model', Tag::class);

        return $this->morphToMany(
            $tagModel,
            'taggable',
            $pivotTable
        )
            ->using(Taggable::class)
            ->withTimestamps()
            ->withPivot(['scope_type', 'scope_id']);
    }

    /*
    |--------------------------------------------------------------------------
    | Scope Helpers
    |--------------------------------------------------------------------------
    */

    /** @return array<string, string|int> */
    protected function buildScopePivotData(?Scope $scope): array
    {
        return $scope ? [
            'scope_type' => $scope->getScopeType(),
            'scope_id' => $scope->getScopeId(),
        ] : [];
    }

    /** @param MorphToMany<covariant Model, covariant Model>|Builder<covariant Model> $query */
    protected function applyScopeFilter(MorphToMany|Builder $query, string $pivotTable, ?Scope $scope): void
    {
        if ($scope !== null) {
            $query->where("{$pivotTable}.scope_type", $scope->getScopeType())
                ->where("{$pivotTable}.scope_id", $scope->getScopeId());
        } else {
            $query->whereNull("{$pivotTable}.scope_type")
                ->whereNull("{$pivotTable}.scope_id");
        }
    }

    /** @param Builder<Model> $query */
    protected function applyScopeFilterToHas(Builder $query, string $pivotTable, ?Scope $scope): void
    {
        if ($scope === null) {
            return;
        }

        $query->where("{$pivotTable}.scope_type", $scope->getScopeType())
            ->where("{$pivotTable}.scope_id", $scope->getScopeId());
    }

    protected function scopedPivotExists(int|string $tagId, ?Scope $scope): bool
    {
        $query = $this->tags()->newPivotStatement()
            ->where('tag_id', $tagId)
            ->where('taggable_type', $this->getMorphClass())
            ->where('taggable_id', $this->getKey());

        if ($scope !== null) {
            $query->where('scope_type', $scope->getScopeType())
                ->where('scope_id', $scope->getScopeId());
        } else {
            $query->whereNull('scope_type')
                ->whereNull('scope_id');
        }

        return $query->exists();
    }

    protected function deleteScopedPivotRecord(int|string $tagId, ?Scope $scope): void
    {
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $pivotQuery = $this->tags()->newPivotStatement()
            ->where('tag_id', $tagId)
            ->where('taggable_type', $this->getMorphClass())
            ->where('taggable_id', $this->getKey());

        if ($scope !== null) {
            $pivotQuery->where('scope_type', $scope->getScopeType())
                ->where('scope_id', $scope->getScopeId());
        } else {
            $pivotQuery->whereNull('scope_type')
                ->whereNull('scope_id');
        }

        $pivotQuery->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | Direct Tagging Methods
    |--------------------------------------------------------------------------
    */

    /** @param string|array<string> $tags */
    public function tag(string|array $tags): static
    {
        $tags = Arr::wrap($tags);
        $tagModels = $this->resolveOrCreateTags($tags);

        $this->tags()->syncWithoutDetaching($tagModels->pluck('id'));
        $this->load('tags');

        return $this;
    }

    /** @param string|array<string> $tags */
    public function untag(string|array $tags): static
    {
        $tags = Arr::wrap($tags);
        $tagIds = $this->resolveTags($tags)->pluck('id');

        $this->tags()->detach($tagIds);
        $this->load('tags');

        return $this;
    }

    /** @param array<string> $tags */
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

    /** @param array<string> $tags */
    public function hasAnyTag(array $tags): bool
    {
        $slugs = collect($tags)->map(fn (string $t) => Str::slug($t));

        return $this->tags->contains(function (Tag $t) use ($slugs) {
            return $slugs->contains($t->slug);
        });
    }

    /** @param array<string> $tags */
    public function hasAllTags(array $tags): bool
    {
        $slugs = collect($tags)->map(fn (string $t) => Str::slug($t));
        $modelSlugs = $this->tags->pluck('slug');

        return $slugs->every(fn ($slug) => $modelSlugs->contains($slug));
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /** @param Builder<Model> $query */
    public function scopeWithTag(Builder $query, string $tag, ?Scope $scope = null): void
    {
        $slug = Str::slug($tag);
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query->whereHas('tags', function (Builder $q) use ($slug, $pivotTable, $scope) {
            $q->where('slug', $slug);
            $this->applyScopeFilterToHas($q, $pivotTable, $scope);
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string>  $tags
     */
    public function scopeWithAnyTag(Builder $query, array $tags, ?Scope $scope = null): void
    {
        $slugs = collect($tags)->map(fn (string $t) => Str::slug($t));
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query->whereHas('tags', function (Builder $q) use ($slugs, $pivotTable, $scope) {
            $q->whereIn('slug', $slugs);
            $this->applyScopeFilterToHas($q, $pivotTable, $scope);
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string>  $tags
     */
    public function scopeWithAllTags(Builder $query, array $tags, ?Scope $scope = null): void
    {
        $slugs = collect($tags)->map(fn (string $t) => Str::slug($t));
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        foreach ($slugs as $slug) {
            $query->whereHas('tags', function (Builder $q) use ($slug, $pivotTable, $scope) {
                $q->where('slug', $slug);
                $this->applyScopeFilterToHas($q, $pivotTable, $scope);
            });
        }
    }

    /** @param Builder<Model> $query */
    public function scopeWithoutTag(Builder $query, string $tag, ?Scope $scope = null): void
    {
        $slug = Str::slug($tag);
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query->whereDoesntHave('tags', function (Builder $q) use ($slug, $pivotTable, $scope) {
            $q->where('slug', $slug);
            $this->applyScopeFilterToHas($q, $pivotTable, $scope);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Resolution Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string>  $tags
     * @return Collection<int, Tag>
     */
    protected function resolveOrCreateTags(array $tags): Collection
    {
        return collect($tags)->map(function (string $tag) {
            $slug = Str::slug($tag);

            return Tag::firstOrCreate(
                ['slug' => $slug, 'parent_id' => null],
                ['name' => $tag]
            );
        });
    }

    /**
     * @param  array<string>  $tags
     * @return Collection<int, Tag>
     */
    protected function resolveTags(array $tags): Collection
    {
        $slugs = collect($tags)->map(fn ($t) => Str::slug($t));

        return Tag::whereIn('slug', $slugs)->whereNull('parent_id')->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Category Tagging Methods
    |--------------------------------------------------------------------------
    */

    public function setTag(string $category, string $value, ?Scope $scope = null): static
    {
        $categoryTag = $this->resolveCategoryTag($category);
        $valueTag = $this->resolveOrCreateValueTag($categoryTag, $value);

        // Remove existing tags in this category for this scope
        $this->removeTagsIn($category, $scope);

        // Attach the new value with scope pivot data
        $pivotData = $this->buildScopePivotData($scope);
        $this->tags()->attach($valueTag->id, $pivotData);
        $this->load('tags');

        return $this;
    }

    public function addTag(string $category, string $value, ?Scope $scope = null): static
    {
        $categoryTag = $this->resolveCategoryTag($category);
        $valueTag = $this->resolveOrCreateValueTag($categoryTag, $value);

        $pivotData = $this->buildScopePivotData($scope);

        if (! $this->scopedPivotExists($valueTag->id, $scope)) {
            $this->tags()->attach($valueTag->id, $pivotData);
        }

        $this->load('tags');

        return $this;
    }

    /** @param array<string> $values */
    public function addTags(string $category, array $values, ?Scope $scope = null): static
    {
        foreach ($values as $value) {
            $this->addTag($category, $value, $scope);
        }

        return $this;
    }

    public function removeTag(string $category, string $value, ?Scope $scope = null): static
    {
        $categoryTag = $this->resolveCategoryTag($category);
        $valueTag = Tag::where('slug', Str::slug($value))
            ->where('parent_id', $categoryTag->id)
            ->first();

        if ($valueTag) {
            $this->deleteScopedPivotRecord($valueTag->id, $scope);
            $this->load('tags');
        }

        return $this;
    }

    public function removeTagsIn(string $category, ?Scope $scope = null): static
    {
        $categoryTag = Tag::where('slug', Str::slug($category))
            ->whereNull('parent_id')
            ->first();

        if (! $categoryTag) {
            return $this;
        }

        $pivotTable = config('taxon.tables.taggables', 'taggables');
        $valueTagIds = $categoryTag->children()->pluck('id');

        $query = $this->tags()->whereIn("{$pivotTable}.tag_id", $valueTagIds);
        $this->applyScopeFilter($query, $pivotTable, $scope);

        /** @var Collection<int, Tag> $matchingTags */
        $matchingTags = $query->get();
        foreach ($matchingTags as $tag) {
            $this->deleteScopedPivotRecord($tag->id, $scope);
        }

        $this->load('tags');

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Category Tag Accessors
    |--------------------------------------------------------------------------
    */

    /** @return Collection<int, Tag> */
    public function tagsIn(string $category, ?Scope $scope = null): Collection
    {
        $categoryTag = Tag::where('slug', Str::slug($category))
            ->whereNull('parent_id')
            ->first();

        if (! $categoryTag) {
            return new Collection;
        }

        $pivotTable = config('taxon.tables.taggables', 'taggables');
        $query = $this->tags()->where('parent_id', $categoryTag->id);
        $this->applyScopeFilter($query, $pivotTable, $scope);

        /** @var Collection<int, Tag> */
        return $query->get();
    }

    public function getTagIn(string $category, ?Scope $scope = null): ?Tag
    {
        /** @var Tag|null */
        return $this->tagsIn($category, $scope)->first();
    }

    public function getTagValueIn(string $category, ?Scope $scope = null): ?string
    {
        return $this->getTagIn($category, $scope)?->slug;
    }

    /*
    |--------------------------------------------------------------------------
    | Category Tag Checks
    |--------------------------------------------------------------------------
    */

    public function hasTagIn(string $category, string $value, ?Scope $scope = null): bool
    {
        return $this->tagsIn($category, $scope)->contains(
            fn (Tag $tag) => $tag->slug === Str::slug($value)
        );
    }

    /** @param array<string> $values */
    public function hasAnyTagIn(string $category, array $values, ?Scope $scope = null): bool
    {
        $slugs = collect($values)->map(fn (string $v) => Str::slug($v));
        $modelSlugs = $this->tagsIn($category, $scope)->pluck('slug');

        return $slugs->contains(fn ($slug) => $modelSlugs->contains($slug));
    }

    /** @param array<string> $values */
    public function hasAllTagsIn(string $category, array $values, ?Scope $scope = null): bool
    {
        $slugs = collect($values)->map(fn (string $v) => Str::slug($v));
        $modelSlugs = $this->tagsIn($category, $scope)->pluck('slug');

        return $slugs->every(fn ($slug) => $modelSlugs->contains($slug));
    }

    /*
    |--------------------------------------------------------------------------
    | Category Query Scopes
    |--------------------------------------------------------------------------
    */

    /** @param Builder<Model> $query */
    public function scopeWithTagIn(Builder $query, string $category, string $value, ?Scope $scope = null): void
    {
        $categorySlug = Str::slug($category);
        $valueSlug = Str::slug($value);
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query->whereHas('tags', function (Builder $q) use ($categorySlug, $valueSlug, $pivotTable, $scope) {
            $q->where('slug', $valueSlug)
                ->whereHas('parent', fn (Builder $p) => $p->where('slug', $categorySlug));
            $this->applyScopeFilterToHas($q, $pivotTable, $scope);
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string>  $values
     */
    public function scopeWithAnyTagIn(Builder $query, string $category, array $values, ?Scope $scope = null): void
    {
        $categorySlug = Str::slug($category);
        $valueSlugs = collect($values)->map(fn (string $v) => Str::slug($v));
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query->whereHas('tags', function (Builder $q) use ($categorySlug, $valueSlugs, $pivotTable, $scope) {
            $q->whereIn('slug', $valueSlugs)
                ->whereHas('parent', fn (Builder $p) => $p->where('slug', $categorySlug));
            $this->applyScopeFilterToHas($q, $pivotTable, $scope);
        });
    }

    /** @param Builder<Model> $query */
    public function scopeWithoutTagIn(Builder $query, string $category, string $value, ?Scope $scope = null): void
    {
        $categorySlug = Str::slug($category);
        $valueSlug = Str::slug($value);
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query->whereDoesntHave('tags', function (Builder $q) use ($categorySlug, $valueSlug, $pivotTable, $scope) {
            $q->where('slug', $valueSlug)
                ->whereHas('parent', fn (Builder $p) => $p->where('slug', $categorySlug));
            $this->applyScopeFilterToHas($q, $pivotTable, $scope);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Category Resolution Helpers
    |--------------------------------------------------------------------------
    */

    protected function resolveCategoryTag(string $category): Tag
    {
        $slug = Str::slug($category);

        $tag = Tag::where('slug', $slug)->whereNull('parent_id')->first();

        if (! $tag && config('taxon.auto_create', true)) {
            $tag = Tag::createCategory($category);
        }

        if (! $tag) {
            throw new TagNotFoundException(
                "Category tag '{$category}' not found."
            );
        }

        return $tag;
    }

    protected function resolveOrCreateValueTag(Tag $category, string $value): Tag
    {
        $slug = Str::slug($value);

        return Tag::firstOrCreate(
            ['slug' => $slug, 'parent_id' => $category->id],
            ['name' => $value, 'assignable' => true]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | TagDefinition Methods
    |--------------------------------------------------------------------------
    */

    /** @param class-string<TagDefinition> $definitionClass */
    public function setTagAs(string $definitionClass, string|BackedEnum $value, ?Scope $scope = null): static
    {
        $this->validateDefinitionValue($definitionClass, $value);

        $valueTag = $definitionClass::valueTag($value);
        $categoryTag = $definitionClass::tag();
        $pivotTable = config('taxon.tables.taggables', 'taggables');
        $existingIds = $categoryTag->children()->pluck('id');

        // Remove only pivot records matching this scope
        $query = $this->tags()->whereIn("{$pivotTable}.tag_id", $existingIds);
        $this->applyScopeFilter($query, $pivotTable, $scope);

        /** @var Tag $tag */
        foreach ($query->get() as $tag) {
            $this->deleteScopedPivotRecord($tag->id, $scope);
        }

        // Attach new value with scope
        $pivotData = $this->buildScopePivotData($scope);
        $this->tags()->attach($valueTag->id, $pivotData);
        $this->load('tags');

        return $this;
    }

    /** @param class-string<TagDefinition> $definitionClass */
    public function addTagAs(string $definitionClass, string|BackedEnum $value, ?Scope $scope = null): static
    {
        $this->validateDefinitionValue($definitionClass, $value);

        $valueTag = $definitionClass::valueTag($value);

        if (! $this->scopedPivotExists($valueTag->id, $scope)) {
            $pivotData = $this->buildScopePivotData($scope);
            $this->tags()->attach($valueTag->id, $pivotData);
        }

        $this->load('tags');

        return $this;
    }

    /** @param class-string<TagDefinition> $definitionClass */
    public function getTagAs(string $definitionClass, ?Scope $scope = null): string|BackedEnum|null
    {
        $categoryTag = $definitionClass::tag();
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query = $this->tags()->where('parent_id', $categoryTag->id);
        $this->applyScopeFilter($query, $pivotTable, $scope);

        /** @var Tag|null $valueTag */
        $valueTag = $query->first();

        if (! $valueTag) {
            return null;
        }

        if ($enum = $definitionClass::enum()) {
            return $enum::tryFrom($valueTag->slug);
        }

        return $valueTag->slug;
    }

    /** @param class-string<TagDefinition> $definitionClass */
    public function hasTagAs(string $definitionClass, string|BackedEnum $value, ?Scope $scope = null): bool
    {
        $slug = $value instanceof BackedEnum ? $value->value : Str::slug($value);
        $categoryTag = $definitionClass::tag();
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query = $this->tags()
            ->where('parent_id', $categoryTag->id)
            ->where('slug', $slug);
        $this->applyScopeFilter($query, $pivotTable, $scope);

        return $query->exists();
    }

    /** @param class-string<TagDefinition> $definitionClass */
    protected function validateDefinitionValue(string $definitionClass, string|BackedEnum $value): void
    {
        if (! $definitionClass::isValidValue($value)) {
            $slug = $value instanceof BackedEnum ? (string) $value->value : $value;

            throw new InvalidTagValueException(
                $slug,
                $definitionClass
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Transition Methods
    |--------------------------------------------------------------------------
    */

    /** @param class-string<TagDefinition> $definitionClass */
    public function transitionTo(string $definitionClass, BackedEnum $to, mixed $user = null, ?Scope $scope = null): static
    {
        $definition = new $definitionClass;
        $from = $this->getTagAs($definitionClass, $scope);

        if (! method_exists($definition, 'canTransition')) {
            return $this->setTagAs($definitionClass, $to, $scope);
        }

        if (! $definition->canTransition($this, $from, $to, $user)) {
            $fromEnum = $from instanceof BackedEnum ? $from : null;

            throw new InvalidTransitionException(
                $this,
                $fromEnum,
                $to
            );
        }

        return $this->setTagAs($definitionClass, $to, $scope);
    }
}
