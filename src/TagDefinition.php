<?php

namespace RobinsonRyan\Taxon;

use BackedEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionMethod;
use RobinsonRyan\Taxon\Exceptions\ImmutableTagDefinitionException;
use RobinsonRyan\Taxon\Models\Tag;

abstract class TagDefinition
{
    public static string $slug;

    public static string $name;

    public static bool $singleSelect = true;

    public static bool $global = false;

    /*
    |--------------------------------------------------------------------------
    | Value Source Configuration
    |--------------------------------------------------------------------------
    */

    public static function enum(): ?string
    {
        return null;
    }

    public static function values(): array
    {
        if ($enum = static::enum()) {
            return array_map(fn ($case) => $case->value, $enum::cases());
        }

        return static::tag()
            ->children()
            ->pluck('slug')
            ->toArray();
    }

    public static function valuesMutable(): bool
    {
        if (static::enum() !== null) {
            return false;
        }

        $reflection = new ReflectionMethod(static::class, 'values');

        return $reflection->getDeclaringClass()->getName() === self::class;
    }

    /*
    |--------------------------------------------------------------------------
    | Tag Resolution
    |--------------------------------------------------------------------------
    */

    public static function tag(): Tag
    {
        $tenantId = static::$global ? null : static::currentTenantId();

        return Tag::firstOrCreate(
            [
                'slug' => static::$slug,
                'parent_id' => null,
                'tenant_id' => $tenantId,
            ],
            [
                'name' => static::$name ?? Str::headline(static::$slug),
                'assignable' => false,
                'single_select' => static::$singleSelect,
            ]
        );
    }

    public static function valueTag(string|BackedEnum $value): Tag
    {
        $slug = $value instanceof BackedEnum ? $value->value : Str::slug($value);
        $name = $value instanceof BackedEnum ? static::enumCaseName($value) : Str::headline($value);

        return Tag::firstOrCreate(
            [
                'slug' => $slug,
                'parent_id' => static::tag()->id,
                'tenant_id' => static::$global ? null : static::currentTenantId(),
            ],
            [
                'name' => $name,
                'assignable' => true,
            ]
        );
    }

    protected static function enumCaseName(BackedEnum $case): string
    {
        return Str::headline(Str::lower($case->name));
    }

    /*
    |--------------------------------------------------------------------------
    | Value Management (Database-backed only)
    |--------------------------------------------------------------------------
    */

    public static function addValue(string $name, ?string $slug = null): Tag
    {
        if (! static::valuesMutable()) {
            throw new ImmutableTagDefinitionException(static::class);
        }

        return Tag::create([
            'name' => $name,
            'slug' => $slug ?? Str::slug($name),
            'parent_id' => static::tag()->id,
            'tenant_id' => static::$global ? null : static::currentTenantId(),
            'assignable' => true,
        ]);
    }

    public static function removeValue(string $slug): bool
    {
        if (! static::valuesMutable()) {
            throw new ImmutableTagDefinitionException(static::class);
        }

        return (bool) static::tag()
            ->children()
            ->where('slug', $slug)
            ->delete();
    }

    public static function firstOrCreateValue(string $slug, ?string $name = null): Tag
    {
        return Tag::firstOrCreate(
            [
                'slug' => $slug,
                'parent_id' => static::tag()->id,
                'tenant_id' => static::$global ? null : static::currentTenantId(),
            ],
            [
                'name' => $name ?? Str::headline($slug),
                'assignable' => true,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public static function isValidValue(string|BackedEnum $value): bool
    {
        $values = static::values();

        if (empty($values)) {
            return true;
        }

        $slug = $value instanceof BackedEnum ? $value->value : Str::slug($value);

        return in_array($slug, $values);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public static function allValueTags(): Collection
    {
        return static::tag()->children;
    }

    protected static function currentTenantId(): ?string
    {
        $config = config('taxon.tenant');

        if (! ($config['enabled'] ?? false)) {
            return null;
        }

        if ($callback = $config['callback'] ?? null) {
            return $callback();
        }

        if (($config['resolver'] ?? null) === 'auth') {
            $attribute = $config['auth_attribute'] ?? 'tenant_id';

            return auth()->user()?->{$attribute};
        }

        return null;
    }
}
