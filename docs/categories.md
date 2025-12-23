# Category Tagging

Categories let you organize tags into groups with single or multi-select behavior.

## Creating Categories

```php
use RobinsonRyan\Taxon\Models\Tag;

// Single-select category (default)
$status = Tag::createCategory('Status');
$status->addChildren(['draft', 'pending', 'published']);

// Multi-select category
$topics = Tag::createCategory('Topics', singleSelect: false);
$topics->addChildren(['php', 'laravel', 'testing']);
```

## Single-Select: setTag

For categories where only one value should be assigned:

```php
$post->setTag('status', 'draft');
$post->setTag('status', 'published'); // Replaces 'draft'

$post->getTagIn('status');        // Tag model
$post->getTagValueIn('status');   // 'published'
```

## Multi-Select: addTag/addTags

For categories where multiple values can be assigned:

```php
$post->addTag('topics', 'php');
$post->addTag('topics', 'laravel');
// or
$post->addTags('topics', ['php', 'laravel']);

$post->tagsIn('topics');          // Collection of Tag models
```

## Removing Category Tags

```php
$post->removeTag('topics', 'php');  // Remove specific
$post->removeTagsIn('topics');       // Remove all in category
```

## Checking Category Tags

```php
$post->hasTagIn('status', 'published');           // bool
$post->hasAnyTagIn('topics', ['php', 'ruby']);    // bool
$post->hasAllTagsIn('topics', ['php', 'laravel']); // bool
```

## Query Scopes

```php
Post::withTagIn('status', 'published')->get();
Post::withAnyTagIn('topics', ['php', 'ruby'])->get();
Post::withoutTagIn('status', 'draft')->get();
```
