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
use RobinsonRyan\Taxon\Exceptions\UnguardedTransitionException;
use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Models\Taggable;

trait HasTags
{
    /**
     * Tag attributes assigned before this model had a key, waiting for one.
     *
     * @var array<string, mixed>
     */
    protected array $pendingTagAttributes = [];

    public static function bootHasTags(): void
    {
        static::saved(function (self $model): void {
            $model->flushPendingTagAttributes();
        });
    }

    /**
     * Persist, then write any assignment that was waiting for a key.
     *
     * The `saved` hook above is the normal path, and it stays: it puts the
     * pivot write inside the model's own save lifecycle, where a consumer's
     * `saved` observer can already see the tag. But `saveQuietly()` and
     * `Model::withoutEvents()` suppress that event, and both are common in
     * seeders — which is precisely where a mass-assigned tag attribute lives.
     * With the event as the only trigger the row persisted with no pivot row
     * and no error, so this override is the guarantee rather than the
     * optimisation. Every persistence entry point Eloquent offers — `create()`,
     * `createQuietly()`, `saveQuietly()`, `push()`, relation saves, factories —
     * funnels through `save()`, so covering it here covers all of them.
     *
     * Flushing clears the pending list before it writes, so on a loud save the
     * event has already emptied it and this call is a no-op.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        $saved = parent::save($options);

        if ($saved) {
            $this->flushPendingTagAttributes();
        }

        return $saved;
    }

    /*
    |--------------------------------------------------------------------------
    | Magic Attribute Access
    |--------------------------------------------------------------------------
    */

    public function getAttribute($key)
    {
        // Check if this key is a declared tag attribute
        if ($this->isTagAttribute($key)) {
            // An assignment made before the model existed has not reached the
            // pivot yet — there is no key to point a pivot row at. Answer from
            // the held value rather than from a query that cannot match
            // anything, or `factory()->make(['status' => 'pending'])->status`
            // reads null and so does any validation between fill() and save().
            if (! $this->exists && array_key_exists($key, $this->pendingTagAttributes)) {
                return $this->pendingTagAttributeValue($key, $this->pendingTagAttributes[$key]);
            }

            return $this->getTagAttributeValue($key);
        }

        return parent::getAttribute($key);
    }

    /**
     * A held assignment, spelled the way the save is going to spell it.
     *
     * Reading before and after the save must agree, so this mirrors the write
     * and read paths exactly: a value tag's slug is the enum's backing value
     * verbatim or `Str::slug()` of a string, and a definition that names an
     * enum reads back as one of its cases.
     */
    protected function pendingTagAttributeValue(string $key, mixed $value): mixed
    {
        if (! is_string($value) && ! $value instanceof BackedEnum) {
            return $value;
        }

        $slug = $value instanceof BackedEnum ? (string) $value->value : Str::slug($value);
        $definition = $this->getTagAttributeDefinition($key);

        if ($definition !== null && ($enum = $definition::enum())) {
            return $enum::tryFrom($slug);
        }

        return $slug;
    }

    public function setAttribute($key, $value)
    {
        // Check if this key is a declared tag attribute
        if ($this->isTagAttribute($key)) {
            // A tag attribute lives in the `taggables` pivot, and a pivot row
            // needs this model's key. On an unsaved model there is not one yet
            // — fill() runs before the INSERT, which is why a factory
            // definition() naming a tag attribute used to die on a null
            // `taggable_id`. Hold the value and write it on `saved` instead.
            if ($this->exists) {
                $this->setTagAttributeValue($key, $value);
            } else {
                $this->pendingTagAttributes[$key] = $value;
            }

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Write the assignments that were waiting for a key, now that there is one.
     *
     * Cleared before the writes rather than after: setTagAttributeValue() runs
     * arbitrary definition code, and anything in there that saves this model
     * again must not replay the same assignments.
     */
    protected function flushPendingTagAttributes(): void
    {
        if ($this->pendingTagAttributes === []) {
            return;
        }

        $pending = $this->pendingTagAttributes;
        $this->pendingTagAttributes = [];

        foreach ($pending as $key => $value) {
            $this->setTagAttributeValue($key, $value);
        }
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

    /** @return MorphToMany<Tag, $this, Taggable, 'pivot'> */
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
        return $scope instanceof Scope ? [
            'scope_type' => $scope->getScopeType(),
            'scope_id' => $scope->getScopeId(),
        ] : [];
    }

    /** @param MorphToMany<covariant Model, covariant Model, covariant \Illuminate\Database\Eloquent\Relations\MorphPivot, 'pivot'>|Builder<covariant Model> $query */
    protected function applyScopeFilter(MorphToMany|Builder $query, string $pivotTable, ?Scope $scope): void
    {
        if ($scope instanceof Scope) {
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
        if (! $scope instanceof Scope) {
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

        if ($scope instanceof Scope) {
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
        $pivotQuery = $this->tags()->newPivotStatement()
            ->where('tag_id', $tagId)
            ->where('taggable_type', $this->getMorphClass())
            ->where('taggable_id', $this->getKey());

        if ($scope instanceof Scope) {
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
    | Slug Matching
    |--------------------------------------------------------------------------
    */

    /**
     * Every slug a caller naming this value could reasonably mean.
     *
     * Reads have to be looser than writes, because the two have never written the
     * same string: `TagDefinition::valueTag()` stores an enum's backing value
     * verbatim (`in_progress`), while the string API stores `Str::slug()` of it
     * (`in-progress`). A read path that slugs unconditionally therefore cannot see
     * an enum-backed tag at all. So match on all three spellings — the value as
     * given, its slug, and that slug re-underscored — and let the write paths go
     * on minting one canonical slug apiece.
     *
     * The set is always a superset of what the read paths matched before, which is
     * what keeps this a fix rather than a change of behaviour.
     *
     * @return list<string>
     */
    protected function tagSlugCandidates(string|BackedEnum $value): array
    {
        $stored = $value instanceof BackedEnum ? (string) $value->value : $value;
        $slug = Str::slug($stored);

        return array_values(array_unique([
            $stored,
            $slug,
            str_replace('-', '_', $slug),
        ]));
    }

    /**
     * The candidate slugs for several values at once, flattened and deduplicated.
     *
     * @param  array<string|BackedEnum>  $values
     * @return list<string>
     */
    protected function tagSlugCandidatesFor(array $values): array
    {
        $candidates = [];

        foreach ($values as $value) {
            foreach ($this->tagSlugCandidates($value) as $candidate) {
                $candidates[] = $candidate;
            }
        }

        return array_values(array_unique($candidates));
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
        $slugs = $this->tagSlugCandidates($tag);

        return $this->tags->contains(fn (Tag $t): bool => in_array($t->slug, $slugs, true));
    }

    /** @param array<string> $tags */
    public function hasAnyTag(array $tags): bool
    {
        $slugs = $this->tagSlugCandidatesFor($tags);

        return $this->tags->contains(fn (Tag $t): bool => in_array($t->slug, $slugs, true));
    }

    /** @param array<string> $tags */
    public function hasAllTags(array $tags): bool
    {
        $modelSlugs = $this->tags->pluck('slug');

        return collect($tags)->every(
            fn (string $tag): bool => $modelSlugs->intersect($this->tagSlugCandidates($tag))->isNotEmpty()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /** @param Builder<Model> $query */
    public function scopeWithTag(Builder $query, string $tag, ?Scope $scope = null): void
    {
        $slugs = $this->tagSlugCandidates($tag);
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query->whereHas('tags', function (Builder $q) use ($slugs, $pivotTable, $scope): void {
            $q->whereIn('slug', $slugs);
            $this->applyScopeFilterToHas($q, $pivotTable, $scope);
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string>  $tags
     */
    public function scopeWithAnyTag(Builder $query, array $tags, ?Scope $scope = null): void
    {
        $slugs = $this->tagSlugCandidatesFor($tags);
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query->whereHas('tags', function (Builder $q) use ($slugs, $pivotTable, $scope): void {
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
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        foreach ($tags as $tag) {
            $slugs = $this->tagSlugCandidates($tag);

            $query->whereHas('tags', function (Builder $q) use ($slugs, $pivotTable, $scope): void {
                $q->whereIn('slug', $slugs);
                $this->applyScopeFilterToHas($q, $pivotTable, $scope);
            });
        }
    }

    /** @param Builder<Model> $query */
    public function scopeWithoutTag(Builder $query, string $tag, ?Scope $scope = null): void
    {
        $slugs = $this->tagSlugCandidates($tag);
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query->whereDoesntHave('tags', function (Builder $q) use ($slugs, $pivotTable, $scope): void {
            $q->whereIn('slug', $slugs);
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
        return Tag::whereIn('slug', $this->tagSlugCandidatesFor($tags))
            ->whereNull('parent_id')
            ->get();
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

        // Every spelling of the value, not just the first one found: where both a
        // `on_hold` and an `on-hold` child exist, taking one at random would drop
        // the caller's removal on the floor.
        $valueTags = Tag::whereIn('slug', $this->tagSlugCandidates($value))
            ->where('parent_id', $categoryTag->id)
            ->get();

        foreach ($valueTags as $valueTag) {
            $this->deleteScopedPivotRecord($valueTag->id, $scope);
        }

        if ($valueTags->isNotEmpty()) {
            $this->load('tags');
        }

        return $this;
    }

    public function removeTagsIn(string $category, ?Scope $scope = null): static
    {
        $categoryTagIds = Tag::whereIn('slug', $this->tagSlugCandidates($category))
            ->whereNull('parent_id')
            ->pluck('id');

        if ($categoryTagIds->isEmpty()) {
            return $this;
        }

        $pivotTable = config('taxon.tables.taggables', 'taggables');
        $valueTagIds = Tag::whereIn('parent_id', $categoryTagIds)->pluck('id');

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
        $categoryTagIds = Tag::whereIn('slug', $this->tagSlugCandidates($category))
            ->whereNull('parent_id')
            ->pluck('id');

        if ($categoryTagIds->isEmpty()) {
            return new Collection;
        }

        $pivotTable = config('taxon.tables.taggables', 'taggables');
        $query = $this->tags()->whereIn('parent_id', $categoryTagIds);
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
        $slugs = $this->tagSlugCandidates($value);

        return $this->tagsIn($category, $scope)->contains(
            fn (Tag $tag): bool => in_array($tag->slug, $slugs, true)
        );
    }

    /** @param array<string> $values */
    public function hasAnyTagIn(string $category, array $values, ?Scope $scope = null): bool
    {
        $slugs = $this->tagSlugCandidatesFor($values);
        $modelSlugs = $this->tagsIn($category, $scope)->pluck('slug');

        return $modelSlugs->contains(fn (string $slug): bool => in_array($slug, $slugs, true));
    }

    /** @param array<string> $values */
    public function hasAllTagsIn(string $category, array $values, ?Scope $scope = null): bool
    {
        $modelSlugs = $this->tagsIn($category, $scope)->pluck('slug');

        return collect($values)->every(
            fn (string $value): bool => $modelSlugs->intersect($this->tagSlugCandidates($value))->isNotEmpty()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Category Query Scopes
    |--------------------------------------------------------------------------
    */

    /** @param Builder<Model> $query */
    public function scopeWithTagIn(Builder $query, string $category, string $value, ?Scope $scope = null): void
    {
        $categorySlugs = $this->tagSlugCandidates($category);
        $valueSlugs = $this->tagSlugCandidates($value);
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query->whereHas('tags', function (Builder $q) use ($categorySlugs, $valueSlugs, $pivotTable, $scope): void {
            $q->whereIn('slug', $valueSlugs)
                ->whereHas('parent', fn (Builder $p) => $p->whereIn('slug', $categorySlugs));
            $this->applyScopeFilterToHas($q, $pivotTable, $scope);
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string>  $values
     */
    public function scopeWithAnyTagIn(Builder $query, string $category, array $values, ?Scope $scope = null): void
    {
        $categorySlugs = $this->tagSlugCandidates($category);
        $valueSlugs = $this->tagSlugCandidatesFor($values);
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query->whereHas('tags', function (Builder $q) use ($categorySlugs, $valueSlugs, $pivotTable, $scope): void {
            $q->whereIn('slug', $valueSlugs)
                ->whereHas('parent', fn (Builder $p) => $p->whereIn('slug', $categorySlugs));
            $this->applyScopeFilterToHas($q, $pivotTable, $scope);
        });
    }

    /** @param Builder<Model> $query */
    public function scopeWithoutTagIn(Builder $query, string $category, string $value, ?Scope $scope = null): void
    {
        $categorySlugs = $this->tagSlugCandidates($category);
        $valueSlugs = $this->tagSlugCandidates($value);
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query->whereDoesntHave('tags', function (Builder $q) use ($categorySlugs, $valueSlugs, $pivotTable, $scope): void {
            $q->whereIn('slug', $valueSlugs)
                ->whereHas('parent', fn (Builder $p) => $p->whereIn('slug', $categorySlugs));
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
        // Looked up by every spelling, created under the canonical one: finding
        // an existing `work_order_status` category beats minting a second,
        // hyphenated root beside it.
        $tag = Tag::whereIn('slug', $this->tagSlugCandidates($category))
            ->whereNull('parent_id')
            ->first();

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
        $categoryTag = $definitionClass::tag();
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query = $this->tags()
            ->where('parent_id', $categoryTag->id)
            ->whereIn('slug', $this->tagSlugCandidates($value));
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

    /**
     * Move this model to $to through the definition's transition guard.
     *
     * A definition that declares no guard — no `transitions()` map and no
     * `canTransition()` override — throws rather than writing: there is nothing
     * here to enforce, and quietly behaving like `setTagAs()` is how a
     * misspelled guard used to disable itself. Call `setTagAs()` directly when
     * an unguarded write is the intent.
     *
     * @param  class-string<TagDefinition>  $definitionClass
     *
     * @throws UnguardedTransitionException when the definition declares no guard
     * @throws InvalidTransitionException when the guard refuses the move
     */
    public function transitionTo(
        string $definitionClass,
        string|BackedEnum $to,
        mixed $user = null,
        ?Scope $scope = null,
    ): static {
        if (! $definitionClass::guardsTransitions()) {
            throw new UnguardedTransitionException($definitionClass);
        }

        $definition = new $definitionClass;
        $from = $this->getTagAs($definitionClass, $scope);

        if (! $definition->canTransition($this, $from, $to, $user)) {
            throw new InvalidTransitionException(
                $this,
                $from,
                $to
            );
        }

        return $this->setTagAs($definitionClass, $to, $scope);
    }
}
