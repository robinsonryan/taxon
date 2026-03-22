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
        static::creating(function (Tag $tag) {
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

    /** @return BelongsTo<Tag, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /** @return HasMany<Tag, $this> */
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

    /** @return Collection<int, static> */
    public function addChildren(array $names): Collection
    {
        return collect($names)->map(fn ($name) => $this->addChild($name));
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
            $this->children->sum(fn (Tag $child) => $child->totalTaggablesCount());
    }
}
