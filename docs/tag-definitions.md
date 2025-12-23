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

Control which state changes are allowed:

```php
class StatusDefinition extends TagDefinition
{
    public static function transitions(): array
    {
        return [
            'draft' => [StatusEnum::PENDING],
            'pending' => [StatusEnum::APPROVED, StatusEnum::DRAFT],
            'approved' => [], // Terminal state
        ];
    }

    public function canTransition($model, ?StatusEnum $from, StatusEnum $to, $user = null): bool
    {
        $allowed = static::transitions()[$from?->value] ?? [];

        if (!in_array($to, $allowed)) {
            return false;
        }

        // Custom logic: only admins can approve
        if ($to === StatusEnum::APPROVED && !$user?->isAdmin()) {
            return false;
        }

        return true;
    }
}

// Use transitions
$post->transitionTo(StatusDefinition::class, StatusEnum::PENDING, $user);

// Throws InvalidTransitionException if not allowed
$post->transitionTo(StatusDefinition::class, StatusEnum::APPROVED, $regularUser);
```
