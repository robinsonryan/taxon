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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public function taggables(?string $type = null): MorphToMany
    {
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query = $this->morphedByMany(
            $type ?? Model::class,
            'taggable',
            $pivotTable
        );

        return $query;
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

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeCategories(Builder $query): Builder
    {
        return $query->whereHas('children');
    }

    public function scopeAssignable(Builder $query): Builder
    {
        return $query->where('assignable', true);
    }

    public function scopeSlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    public function scopeChildrenOf(Builder $query, string|int $parent): Builder
    {
        if (is_string($parent)) {
            $parent = static::where('slug', $parent)->value('id');
        }

        return $query->where('parent_id', $parent);
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
        return static::create([
            'name' => $name,
            'slug' => $slug ?? Str::slug($name),
            'parent_id' => null,
            'tenant_id' => $tenantId,
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
        return static::create([
            'name' => $name,
            'slug' => $slug ?? Str::slug($name),
            'parent_id' => $parentId,
            'tenant_id' => $tenantId,
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

    public function addChildren(array $names): Collection
    {
        return collect($names)->map(fn ($name) => $this->addChild($name));
    }

    public function syncChildren(array $values): Collection
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

        return $this->delete();
    }

    public function forceDelete(): bool
    {
        return $this->delete();
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
