<?php

namespace RobinsonRyan\Taxon;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
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

    /** @return array<int, string> */
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
                config('taxon.tenant.column', 'tenant_id') => $tenantId,
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
                config('taxon.tenant.column', 'tenant_id') => static::$global ? null : static::currentTenantId(),
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
            config('taxon.tenant.column', 'tenant_id') => static::$global ? null : static::currentTenantId(),
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
                config('taxon.tenant.column', 'tenant_id') => static::$global ? null : static::currentTenantId(),
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

        if ($values === []) {
            return true;
        }

        $slug = $value instanceof BackedEnum ? $value->value : Str::slug($value);

        return in_array($slug, $values);
    }

    /*
    |--------------------------------------------------------------------------
    | Transitions
    |--------------------------------------------------------------------------
    |
    | A definition declares its state machine here. `transitions()` returning
    | null means "this definition has no transition guard at all", and
    | HasTags::transitionTo() refuses to write through it — use setTagAs() when
    | an unguarded write is what you want.
    |
    */

    /**
     * The allowed target states, keyed by source state value.
     *
     * Null means the definition declares no state machine. Keys are state
     * *values* (an enum's backing value, or a slug); the listed targets may be
     * either enum cases or strings.
     *
     * @return array<string, iterable<string|BackedEnum>>|null
     */
    public static function transitions(): ?array
    {
        return null;
    }

    /**
     * The state a model is expected to enter first, before it holds any value
     * for this definition.
     *
     * Null means "no declared initial state", in which case the default guard
     * lets any valid value be the first one.
     */
    public static function default(): string|BackedEnum|null
    {
        return null;
    }

    /**
     * Whether this definition guards its transitions at all — either by
     * declaring a `transitions()` map or by overriding `canTransition()`.
     */
    public static function guardsTransitions(): bool
    {
        if (static::transitions() !== null) {
            return true;
        }

        $reflection = new ReflectionMethod(static::class, 'canTransition');

        return $reflection->getDeclaringClass()->getName() !== self::class;
    }

    /**
     * Reduce a state to the value it is stored under — an enum's backing value,
     * or a slugged string. Guards should compare states through this rather
     * than with `===`, so `'In Progress'`, `'in-progress'` and the matching enum
     * case all answer alike.
     */
    public static function normalizeState(string|BackedEnum $state): string
    {
        return $state instanceof BackedEnum ? (string) $state->value : Str::slug($state);
    }

    /**
     * Every state the `transitions()` map mentions — its keys and its targets,
     * normalized, in declaration order. Empty when no map is declared.
     *
     * This is the map's own vocabulary, and it is what an initial state is
     * checked against when the definition declares no `default()`.
     *
     * @return list<string>
     */
    public static function declaredStates(): array
    {
        $transitions = static::transitions();

        if ($transitions === null) {
            return [];
        }

        $states = [];

        foreach ($transitions as $from => $targets) {
            $states[] = static::normalizeState($from);

            foreach ($targets as $target) {
                $states[] = static::normalizeState($target);
            }
        }

        return array_values(array_unique($states));
    }

    /**
     * Whether $model may move from $from to $to.
     *
     * The default implementation answers from `transitions()`, and refuses
     * everything when no map is declared. Override it for guards that need the
     * model or the user — call `parent::canTransition()` first to keep the map
     * authoritative and add the extra rule on top.
     */
    public function canTransition(
        Model $model,
        string|BackedEnum|null $from,
        string|BackedEnum $to,
        mixed $user = null,
    ): bool {
        if (static::transitions() === null) {
            return false;
        }

        if ($from === null) {
            $default = static::default();

            // With no declared default, the first state is anything the map
            // knows about. Deferring to isValidValue() here was a hole: a
            // database-backed definition has no value tags until something is
            // written, `values()` is therefore empty, and "no declared values"
            // means "everything is valid" — so the very first write could put
            // the model into a state the machine has no rules for, with no
            // transition back out.
            return $default === null
                ? in_array(static::normalizeState($to), static::declaredStates(), true)
                : static::normalizeState($default) === static::normalizeState($to);
        }

        foreach (static::transitionsFrom($from) as $candidate) {
            if (static::normalizeState($candidate) === static::normalizeState($to)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The map's targets for $from.
     *
     * @return iterable<string|BackedEnum>
     */
    protected static function transitionsFrom(string|BackedEnum $from): iterable
    {
        $transitions = static::transitions() ?? [];

        return $transitions[static::normalizeState($from)] ?? [];
    }

    /**
     * The states $model may move to right now, in the order the map declares
     * them, filtered through `canTransition()` so code-level guards apply too.
     *
     * @return list<string|BackedEnum>
     */
    public function availableTransitions(Model $model, mixed $user = null): array
    {
        $from = $this->currentState($model);

        if ($from === null) {
            $default = static::default();

            return $default !== null && $this->canTransition($model, null, $default, $user)
                ? [$default]
                : [];
        }

        // Without a map there is nothing to enumerate: a definition that guards
        // only in code can answer canTransition() but cannot list its options.
        $candidates = static::transitionsFrom($from);
        $available = [];

        foreach ($candidates as $candidate) {
            if ($this->canTransition($model, $from, $candidate, $user)) {
                $available[] = $candidate;
            }
        }

        return $available;
    }

    /** The value $model currently holds for this definition, if it can hold one at all. */
    protected function currentState(Model $model): string|BackedEnum|null
    {
        if (! method_exists($model, 'getTagAs')) {
            return null;
        }

        /** @var string|BackedEnum|null $current */
        $current = $model->getTagAs(static::class);

        return $current;
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /** @return Collection<int, Tag> */
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
