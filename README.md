# Taxon

A flexible hierarchical tagging system for Laravel with tenant scoping and tag definitions.

## Features

- **Tier 1: Convention-Based Tagging** - Simple, zero-config tagging
- **Tier 2: Class-Based Tag Definitions** - Enum/array/database-backed values with guards
- **Hierarchical Tags** - Categories and nested tag structures
- **Tenant Scoping** - Multi-tenant support out of the box
- **Magic Attribute Access** - Access tags as model properties

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x

## Installation

```bash
composer require robinsonryan/taxon
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=taxon-config
php artisan vendor:publish --tag=taxon-migrations
php artisan migrate
```

## Quick Start

```php
use RobinsonRyan\Taxon\HasTags;

class Post extends Model
{
    use HasTags;
}

// Direct tagging
$post->tag('important');
$post->tag(['idea', 'todo']);

// Category tagging
$post->setTag('status', 'published');
$post->setTag('priority', 'high');

// Query by tags
Post::withTag('important')->get();
Post::withAllTags(['idea', 'todo'])->get();
```

## Documentation

See the [docs](docs/) folder for full documentation.

## License

MIT
