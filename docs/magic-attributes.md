# Magic Attributes

Access tags as model properties using the `$tagAttributes` array.

## String-Based Categories

```php
class Post extends Model
{
    use HasTags;

    protected array $tagAttributes = [
        'status',
        'priority',
    ];
}

// Get and set as properties
$post->status = 'published';
echo $post->status; // 'published'

$post->priority = 'high';
echo $post->priority; // 'high'
```

## TagDefinition-Backed Attributes

```php
class Post extends Model
{
    use HasTags;

    protected array $tagAttributes = [
        'status' => StatusDefinition::class,
    ];
}

// Returns typed enum values
$post->status = StatusEnum::PUBLISHED;
echo $post->status; // StatusEnum::PUBLISHED

// String values are converted to enum
$post->status = 'published';
echo $post->status; // StatusEnum::PUBLISHED
```

## How It Works

The `$tagAttributes` array can contain:

1. **String values**: `['status']` - Maps to category tagging (`setTag/getTagValueIn`)
2. **Class values**: `['status' => StatusDefinition::class]` - Maps to typed definition access (`setTagAs/getTagAs`)

Regular model attributes are unaffected - only keys declared in `$tagAttributes` are intercepted.
