# API Reference

## HasTags Trait Methods

### Direct Tagging

| Method | Description |
|--------|-------------|
| `tag(string\|array $tags)` | Add one or more tags |
| `untag(string\|array $tags)` | Remove one or more tags |
| `retag(array $tags)` | Replace all tags |
| `detachAllTags()` | Remove all tags |

### Direct Tag Checks

| Method | Description |
|--------|-------------|
| `hasTag(string $tag)` | Check if model has tag |
| `hasAnyTag(array $tags)` | Check if model has any of the tags |
| `hasAllTags(array $tags)` | Check if model has all the tags |

### Category Tagging

| Method | Description |
|--------|-------------|
| `setTag(string $category, string $value)` | Set single tag in category |
| `addTag(string $category, string $value)` | Add tag to category |
| `addTags(string $category, array $values)` | Add multiple tags to category |
| `removeTag(string $category, string $value)` | Remove specific tag from category |
| `removeTagsIn(string $category)` | Remove all tags in category |

### Category Accessors

| Method | Description |
|--------|-------------|
| `tagsIn(string $category)` | Get all tags in category |
| `getTagIn(string $category)` | Get first tag in category |
| `getTagValueIn(string $category)` | Get slug of first tag in category |

### Category Checks

| Method | Description |
|--------|-------------|
| `hasTagIn(string $category, string $value)` | Check for specific tag |
| `hasAnyTagIn(string $category, array $values)` | Check for any tag |
| `hasAllTagsIn(string $category, array $values)` | Check for all tags |

### TagDefinition Methods

| Method | Description |
|--------|-------------|
| `setTagAs(string $class, mixed $value)` | Set tag using definition |
| `addTagAs(string $class, mixed $value)` | Add tag using definition |
| `getTagAs(string $class)` | Get typed value via definition |
| `hasTagAs(string $class, mixed $value)` | Check value via definition |
| `transitionTo(string $class, BackedEnum $to, $user)` | Guarded state transition |

## Query Scopes

### Direct Tag Scopes

```php
Model::withTag(string $tag)
Model::withAnyTag(array $tags)
Model::withAllTags(array $tags)
Model::withoutTag(string $tag)
```

### Category Tag Scopes

```php
Model::withTagIn(string $category, string $value)
Model::withAnyTagIn(string $category, array $values)
Model::withoutTagIn(string $category, string $value)
```

## Tag Model Methods

### Factory Methods

```php
Tag::createCategory(string $name, ?string $tenantId = null, bool $singleSelect = true)
Tag::createValue(string $name, int|string $parentId, ?string $tenantId = null)
```

### Child Management

```php
$tag->addChild(string $name)
$tag->addChildren(array $names)
$tag->syncChildren(array $values)
```

### Query Scopes

```php
Tag::roots()
Tag::categories()
Tag::assignable()
Tag::slug(string $slug)
Tag::childrenOf(string|int $parent)
```

## TagDefinition Static Methods

| Method | Description |
|--------|-------------|
| `tag()` | Get or create the category tag |
| `valueTag(mixed $value)` | Get or create a value tag |
| `values()` | Get array of valid values |
| `valuesMutable()` | Check if values can be added/removed |
| `isValidValue(mixed $value)` | Validate a value |
| `addValue(string $name)` | Add value (mutable only) |
| `removeValue(string $slug)` | Remove value (mutable only) |
