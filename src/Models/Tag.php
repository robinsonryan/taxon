<?php

namespace RobinsonRyan\Taxon\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RobinsonRyan\Taxon\Concerns\ConfiguresIdentifiers;
use RobinsonRyan\Taxon\Exceptions\CircularTagHierarchyException;
use RobinsonRyan\Taxon\Exceptions\DuplicateTagSlugException;
use RobinsonRyan\Taxon\Exceptions\TagInUseException;
use RobinsonRyan\Taxon\HasTags;

/**
 * @property int|string $id
 * @property string $name
 * @property string $slug
 * @property int|string|null $parent_id
 * @property bool $assignable
 * @property bool $single_select
 * @property array<string, mixed>|null $meta
 * @property-read Tag|null $parent
 * @property-read Collection<int, Tag> $children
 * @property-read Collection<int, Tag> $tags
 */
class Tag extends Model
{
    use ConfiguresIdentifiers;
    use HasTags;

    protected $guarded = [];

    protected $casts = [
        'assignable' => 'boolean',
        'single_select' => 'boolean',
        'meta' => 'array',
    ];

    protected $attributes = [
        'assignable' => true,
        'single_select' => true,
    ];

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::creating(function (Tag $tag): void {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Table Configuration
    |--------------------------------------------------------------------------
    */

    public function getTable(): string
    {
        return config('taxon.tables.tags', 'tags');
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /** @return BelongsTo<static, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /** @return HasMany<static, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    /**
     * @param  class-string<Model>|null  $type
     * @return MorphToMany<Model, $this>
     */
    public function taggables(?string $type = null): MorphToMany
    {
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        /** @var class-string<Model> $morphType */
        $morphType = $type ?? Model::class;

        return $this->morphedByMany(
            $morphType,
            'taggable',
            $pivotTable
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Helpers
    |--------------------------------------------------------------------------
    */

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isCategory(): bool
    {
        return $this->children()->exists();
    }

    public function isLeaf(): bool
    {
        return ! $this->isCategory();
    }

    public function isAssignable(): bool
    {
        return $this->assignable;
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /** @param Builder<Tag> $query */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /** @param Builder<Tag> $query */
    public function scopeCategories(Builder $query): void
    {
        $query->whereHas('children');
    }

    /** @param Builder<Tag> $query */
    public function scopeAssignable(Builder $query): void
    {
        $query->where('assignable', true);
    }

    /** @param Builder<Tag> $query */
    public function scopeSlug(Builder $query, string $slug): void
    {
        $query->where('slug', $slug);
    }

    /** @param Builder<Tag> $query */
    public function scopeChildrenOf(Builder $query, string|int $parent): void
    {
        if (is_string($parent)) {
            $parent = static::where('slug', $parent)->value('id');
        }

        $query->where('parent_id', $parent);
    }

    /** @param Builder<Tag> $query */
    public function scopeInCategory(Builder $query, string $category): void
    {
        $query->whereHas('parent', fn (Builder $q) => $q
            ->where('slug', Str::slug($category))
            ->whereNull('parent_id'));
    }

    /*
    |--------------------------------------------------------------------------
    | Factory Methods
    |--------------------------------------------------------------------------
    */

    public static function createCategory(
        string $name,
        ?string $tenantId = null,
        bool $singleSelect = true,
        ?string $slug = null,
    ): static {
        /** @var static */
        return static::create([
            'name' => $name,
            'slug' => $slug ?? Str::slug($name),
            'parent_id' => null,
            config('taxon.tenant.column', 'tenant_id') => $tenantId,
            'assignable' => false,
            'single_select' => $singleSelect,
        ]);
    }

    public static function createValue(
        string $name,
        string|int $parentId,
        ?string $tenantId = null,
        ?string $slug = null,
    ): static {
        /** @var static */
        return static::create([
            'name' => $name,
            'slug' => $slug ?? Str::slug($name),
            'parent_id' => $parentId,
            config('taxon.tenant.column', 'tenant_id') => $tenantId,
            'assignable' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Child Management
    |--------------------------------------------------------------------------
    */

    public function addChild(string $name, ?string $slug = null): static
    {
        return static::createValue(
            name: $name,
            parentId: $this->id,
            tenantId: $this->{config('taxon.tenant.column', 'tenant_id')},
            slug: $slug,
        );
    }

    /**
     * @param  array<int, string>  $names
     * @return Collection<int, static>
     */
    public function addChildren(array $names): Collection
    {
        return collect($names)->map(fn (string $name): static => $this->addChild($name));
    }

    /**
     * @param  array<array{id?: int|string, name: string}>  $values
     * @return \Illuminate\Database\Eloquent\Collection<int, Tag>
     */
    public function syncChildren(array $values): \Illuminate\Database\Eloquent\Collection
    {
        $keepIds = [];

        foreach ($values as $value) {
            if (isset($value['id'])) {
                $this->children()->where('id', $value['id'])->update([
                    'name' => $value['name'],
                    'slug' => Str::slug($value['name']),
                ]);
                $keepIds[] = $value['id'];
            } else {
                $child = $this->addChild($value['name']);
                $keepIds[] = $child->id;
            }
        }

        $this->children()->whereNotIn('id', $keepIds)->delete();

        return $this->children()->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Trees
    |--------------------------------------------------------------------------
    |
    | Tags nest to any depth through `parent_id`. HasTags' *category* API is a
    | separate, deliberately flatter thing — a category and its values, two
    | levels, no more — and nothing here changes it. These methods are for
    | working with the tag tree directly.
    |
    */

    /** This tag's slugs from the root down, joined with '/': `topics/web/frontend`. */
    public function path(): string
    {
        $slugs = $this->ancestors()->pluck('slug')->all();
        $slugs[] = $this->slug;

        return implode('/', $slugs);
    }

    /**
     * The tag at a '/'-joined slug path, walked from a root tag down.
     *
     * Tenant-scoped the way the rest of the package is: a null $tenantId matches
     * tags with no tenant, not tags of every tenant. Segments are slugged, so
     * `Topics/Web` and `topics/web` address the same tag. Costs one query per
     * segment.
     */
    public static function resolvePath(string $path, ?string $tenantId = null): ?static
    {
        $segments = array_values(array_filter(
            array_map(static fn (string $segment): string => Str::slug($segment), explode('/', $path)),
            static fn (string $segment): bool => $segment !== '',
        ));

        if ($segments === []) {
            return null;
        }

        $tenantColumn = config('taxon.tenant.column', 'tenant_id');
        $current = null;

        foreach ($segments as $segment) {
            $query = static::query()->where('slug', $segment);

            $current === null
                ? $query->whereNull('parent_id')
                : $query->where('parent_id', $current->getKey());

            $tenantId === null
                ? $query->whereNull($tenantColumn)
                : $query->where($tenantColumn, $tenantId);

            $current = $query->first();

            if ($current === null) {
                return null;
            }
        }

        return $current;
    }

    /**
     * Every tag between the root and this one, root first. Two queries whatever
     * the depth: one recursive CTE for the ids, one to hydrate them.
     *
     * @return Collection<int, static>
     */
    public function ancestors(): Collection
    {
        $table = $this->getTable();
        $key = $this->getKeyName();

        return $this->hydrateInOrder($this->getConnection()->select(
            "with recursive taxon_ancestry as (
                select {$key} as id, parent_id, 0 as depth from {$table} where {$key} = ?
                union all
                select parent.{$key}, parent.parent_id, taxon_ancestry.depth + 1
                from {$table} parent
                inner join taxon_ancestry on parent.{$key} = taxon_ancestry.parent_id
            )
            select id from taxon_ancestry where depth > 0 order by depth desc",
            [$this->getKey()],
        ));
    }

    /**
     * Every tag below this one, nearest level first. Two queries whatever the
     * size of the subtree.
     *
     * @return Collection<int, static>
     */
    public function descendants(): Collection
    {
        $table = $this->getTable();
        $key = $this->getKeyName();

        return $this->hydrateInOrder($this->getConnection()->select(
            "with recursive taxon_subtree as (
                select {$key} as id, 0 as depth from {$table} where parent_id = ?
                union all
                select child.{$key}, taxon_subtree.depth + 1
                from {$table} child
                inner join taxon_subtree on child.parent_id = taxon_subtree.id
            )
            select id from taxon_subtree order by depth asc, id asc",
            [$this->getKey()],
        ));
    }

    /**
     * Re-parent this tag; pass null to make it a root.
     *
     * Two things are checked before the write, because neither is something the
     * caller can recover from afterwards: moving a tag into its own subtree
     * (which the database would happily store, and which would then hang every
     * ancestor walk), and landing on a slug the destination already holds
     * (which the unique index would reject as a QueryException, taking the
     * surrounding PostgreSQL transaction down with it).
     *
     * @throws CircularTagHierarchyException when the destination is this tag or one of its descendants
     * @throws DuplicateTagSlugException when the destination already holds this slug for this tenant
     */
    public function moveTo(?self $newParent): static
    {
        if ($newParent instanceof Tag) {
            $this->assertNotInOwnSubtree($newParent);
        }

        $this->assertSlugIsFreeUnder($newParent);

        $this->parent_id = $newParent?->getKey();
        $this->save();
        $this->unsetRelation('parent');

        return $this;
    }

    protected function assertNotInOwnSubtree(self $newParent): void
    {
        if ($newParent->is($this)) {
            throw new CircularTagHierarchyException($this, $newParent);
        }

        // Asking the destination for its ancestors, rather than asking this tag
        // for its descendants, keeps the check at two queries however large the
        // subtree being moved.
        foreach ($newParent->ancestors() as $ancestor) {
            if ($ancestor->is($this)) {
                throw new CircularTagHierarchyException($this, $newParent);
            }
        }
    }

    protected function assertSlugIsFreeUnder(?self $newParent): void
    {
        $tenantColumn = config('taxon.tenant.column', 'tenant_id');
        $tenantId = $this->{$tenantColumn};

        $query = static::query()
            ->where('slug', $this->slug)
            ->whereKeyNot($this->getKey());

        $newParent instanceof Tag
            ? $query->where('parent_id', $newParent->getKey())
            : $query->whereNull('parent_id');

        $tenantId === null
            ? $query->whereNull($tenantColumn)
            : $query->where($tenantColumn, $tenantId);

        if ($query->exists()) {
            throw new DuplicateTagSlugException(
                $this->slug,
                $newParent?->getKey(),
                is_string($tenantId) ? $tenantId : null,
            );
        }
    }

    /**
     * Hydrate rows of `{id: mixed}` back into models, preserving the order the
     * recursive query returned them in.
     *
     * @param  array<int, object{id: mixed}>  $rows
     * @return Collection<int, static>
     */
    protected function hydrateInOrder(array $rows): Collection
    {
        $ids = [];

        foreach ($rows as $row) {
            $ids[] = $row->id;
        }

        if ($ids === []) {
            return new Collection;
        }

        $byId = [];

        foreach (static::query()->whereIn($this->getKeyName(), $ids)->get() as $model) {
            $byId[(string) $model->getKey()] = $model;
        }

        $ordered = [];

        foreach ($ids as $id) {
            if (isset($byId[(string) $id])) {
                $ordered[] = $byId[(string) $id];
            }
        }

        return new Collection($ordered);
    }

    /*
    |--------------------------------------------------------------------------
    | Deletion
    |--------------------------------------------------------------------------
    */

    public function safeDelete(): bool
    {
        $this->assertNotInUse();

        return (bool) $this->delete();
    }

    public function forceDelete(): bool
    {
        return (bool) $this->delete();
    }

    protected function assertNotInUse(): void
    {
        if ($this->taggablesCount() > 0) {
            throw new TagInUseException($this);
        }

        $this->children->each(fn (Tag $child) => $child->assertNotInUse());
    }

    public function taggablesCount(): int
    {
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        return DB::table($pivotTable)
            ->where('tag_id', $this->id)
            ->count();
    }

    public function totalTaggablesCount(): int
    {
        return $this->taggablesCount() +
            $this->children->sum(fn (Tag $child): int => $child->totalTaggablesCount());
    }
}
