# Basic Usage

## Adding the Trait

```php
use RobinsonRyan\Taxon\HasTags;

class Article extends Model
{
    use HasTags;
}
```

## Direct Tagging

### Adding Tags

```php
$article->tag('featured');
$article->tag(['php', 'laravel', 'tutorial']);
```

### Removing Tags

```php
$article->untag('featured');
$article->untag(['php', 'laravel']);
$article->detachAllTags();
```

### Syncing Tags

```php
$article->retag(['new', 'tags', 'only']);
```

## Checking Tags

```php
$article->hasTag('featured');              // bool
$article->hasAnyTag(['php', 'javascript']); // bool
$article->hasAllTags(['php', 'laravel']);   // bool
```

## Query Scopes

```php
Article::withTag('featured')->get();
Article::withAnyTag(['php', 'javascript'])->get();
Article::withAllTags(['php', 'laravel'])->get();
Article::withoutTag('draft')->get();
```

## The Tags Relationship

Access tags through the standard Eloquent relationship:

```php
$article->tags;                    // Collection of Tag models
$article->tags->pluck('slug');     // ['php', 'laravel']
$article->tags()->count();         // 2
```
