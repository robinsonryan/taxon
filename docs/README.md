# Taxon

A flexible hierarchical tagging system for Laravel with tenant scoping and class-based tag definitions.

## Features

- **Direct Tagging**: Simple tag/untag operations on any model
- **Category Tagging**: Organize tags into categories with single/multi-select behavior
- **Tag Definitions**: Class-based definitions with enum validation and transition guards
- **Tenant Scoping**: Multi-tenant support with configurable resolution
- **Tags Tag Tags**: Build RBAC systems or any graph-like relationships

## Quick Start

```php
use RobinsonRyan\Taxon\HasTags;

class Post extends Model
{
    use HasTags;
}

// Direct tagging
$post->tag('featured');
$post->tag(['php', 'laravel', 'tutorial']);

// Category tagging
$post->setTag('status', 'published');
$post->addTag('topics', 'web-development');

// Query scopes
Post::withTag('featured')->get();
Post::withTagIn('status', 'published')->get();
```

## Documentation

- [Installation](installation.md)
- [Basic Usage](basic-usage.md)
- [Categories](categories.md)
- [Magic Attributes](magic-attributes.md)
- [Tag Definitions](tag-definitions.md)
- [Tenant Scoping](tenant-scoping.md)
- [API Reference](api-reference.md)
