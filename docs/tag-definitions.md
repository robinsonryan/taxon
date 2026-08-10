# Tag Definitions

Tag Definitions provide class-based configuration for structured tagging with validation and transition guards.

## Creating a Definition

```php
use RobinsonRyan\Taxon\TagDefinition;

enum StatusEnum: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
}

class StatusDefinition extends TagDefinition
{
    public static string $slug = 'status';
    public static string $name = 'Status';
    public static bool $singleSelect = true;
    public static bool $global = true;

    public static function enum(): string
    {
        return StatusEnum::class;
    }
}
```

## Using Definitions

```php
// Set value (validates against enum)
$post->setTagAs(StatusDefinition::class, StatusEnum::PENDING);
$post->setTagAs(StatusDefinition::class, 'pending'); // Also works

// Get typed value
$status = $post->getTagAs(StatusDefinition::class);
// Returns: StatusEnum::PENDING

// Check value
$post->hasTagAs(StatusDefinition::class, StatusEnum::PENDING); // true
```

## Enum-Backed vs Database-Backed

### Enum-Backed (Immutable)

```php
class StatusDefinition extends TagDefinition
{
    public static function enum(): string
    {
        return StatusEnum::class;
    }
}

StatusDefinition::valuesMutable(); // false
StatusDefinition::values();        // ['draft', 'pending', 'approved']
```

### Database-Backed (Mutable)

```php
class PriorityDefinition extends TagDefinition
{
    public static string $slug = 'priority';
    // No enum() method
}

PriorityDefinition::valuesMutable(); // true
PriorityDefinition::addValue('High');
PriorityDefinition::addValue('Low');
PriorityDefinition::removeValue('low');
```

## Transition Guards

A definition can describe which state changes are allowed. The whole contract is
inherited from `TagDefinition` — declaring the map is usually all you write.

### Declare the state machine

```php
class StatusDefinition extends TagDefinition
{
    public static string $slug = 'status';
    public static string $name = 'Status';

    public static function enum(): string
    {
        return StatusEnum::class;
    }

    // The state a model is expected to enter first.
    public static function default(): StatusEnum
    {
        return StatusEnum::DRAFT;
    }

    // Keyed by source state VALUE; targets may be enum cases or strings.
    public static function transitions(): array
    {
        return [
            'draft'    => [StatusEnum::PENDING],
            'pending'  => [StatusEnum::APPROVED, StatusEnum::DRAFT],
            'approved' => [],   // terminal
        ];
    }
}
```

```php
$post->transitionTo(StatusDefinition::class, StatusEnum::PENDING, $user);
$post->transitionTo(StatusDefinition::class, 'pending', $user);   // same thing

// Throws InvalidTransitionException — 'approved' is not reachable from 'draft'
$post->transitionTo(StatusDefinition::class, StatusEnum::APPROVED);

// What can this model do right now, in map order?
(new StatusDefinition)->availableTransitions($post, $user);
```

`default()` decides the **first** move, when the model holds no value yet: only
the default is reachable. Leave `default()` unset and the first state may be any
state the map **mentions** — a key, or a target on the right of an arrow. It is
never something the map has no rules for: a state outside the map's own
vocabulary would leave the model wedged, holding a value with no transition out
of it.

States are compared through `normalizeState()` — an enum case, its backing value
and a human-typed label all answer alike, so `StatusEnum::PENDING`, `'pending'`
and `'Pending'` are one state. Compare that way in your own guards too.

### Add a rule the map cannot express

Override `canTransition()`, and call `parent::` first so the map stays
authoritative:

```php
public function canTransition(
    Model $model,
    string|BackedEnum|null $from,
    string|BackedEnum $to,
    mixed $user = null,
): bool {
    if (! parent::canTransition($model, $from, $to, $user)) {
        return false;
    }

    // Only admins can approve.
    if (static::normalizeState($to) !== StatusEnum::APPROVED->value) {
        return true;
    }

    return $user?->isAdmin() ?? false;
}
```

The parameter types are fixed by the base class — PHP will not accept a
narrower one (`?StatusEnum $from`). Narrow inside the body instead.

`availableTransitions()` filters the map through `canTransition()`, so a rule
added here shows up in the list without further work. A definition that guards
*only* in code, with no map, can still answer `canTransition()` — but
`availableTransitions()` returns `[]`, because there is nothing to enumerate.

### A definition with no guard refuses to transition

```php
class PriorityDefinition extends TagDefinition
{
    public static string $slug = 'priority';
    // no transitions(), no canTransition()
}

$post->transitionTo(PriorityDefinition::class, 'high');
// UnguardedTransitionException

$post->setTagAs(PriorityDefinition::class, 'high');   // this is the way
```

`transitionTo()` exists to enforce something. Where there is nothing to enforce
it throws rather than writing, so a renamed or misspelled guard fails loudly
instead of turning itself off. `setTagAs()` remains the unguarded write.

### Method reference

| Method | Kind | Default | Purpose |
|---|---|---|---|
| `transitions(): ?array` | static | `null` | The state machine, keyed by source state value |
| `default(): string\|BackedEnum\|null` | static | `null` | The state a model may enter first |
| `declaredStates(): array` | static | derived | Every state the map mentions — keys and targets, normalized |
| `guardsTransitions(): bool` | static | derived | True if a map is declared or `canTransition()` is overridden |
| `normalizeState(string\|BackedEnum): string` | static | — | Reduce a state to its stored value |
| `canTransition(Model, from, to, user): bool` | instance | reads the map | Whether one move is allowed |
| `availableTransitions(Model, user): array` | instance | walks the map | The moves allowed right now |
