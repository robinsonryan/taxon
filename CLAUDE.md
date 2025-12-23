# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Taxon is a flexible hierarchical tagging system for Laravel with tenant scoping and class-based tag definitions.

**Namespace:** `RobinsonRyan\Taxon`
**PHP:** 8.2+
**Laravel:** 11.x, 12.x

## Development Commands

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run single test
./vendor/bin/pest --filter="test name"

# Run tests with coverage
composer test:coverage

# Static analysis (PHPStan level 8)
composer analyze

# Code formatting (Laravel Pint)
composer lint

# Full quality check (lint, analyze, test)
composer quality
```

### DDEV Commands

```bash
ddev start           # Start environment
ddev test            # Run tests
ddev quality         # Full quality checks
```

## Architecture

### Two-Tier Tagging System

**Tier 1: Convention-Based** - Zero-config tagging
- `$model->tag('important')` - Direct tags
- `$model->setTag('status', 'pending')` - Category-based tags
- Tags auto-created on first use

**Tier 2: Class-Based TagDefinitions** - Structured tags with guards
- Enum-backed (immutable) or database-backed (mutable) values
- Transition guards with `canTransition()` and `transitionTo()`
- Magic property accessors via `$tagAttributes`

### Key Components

| Component | Location | Purpose |
|-----------|----------|---------|
| `HasTags` trait | `src/HasTags.php` | Core tagging for models |
| `TagDefinition` | `src/TagDefinition.php` | Base class for structured tags |
| `Tag` model | `src/Models/Tag.php` | Hierarchical tag storage |
| `ConfiguresIdentifiers` | `src/Concerns/` | UUID7/incrementing ID support |

### Exceptions

- `TagNotFoundException` - Category not found when auto_create=false
- `TagInUseException` - Cannot delete tag with active taggables
- `InvalidTagValueException` - Value not valid for TagDefinition
- `InvalidTransitionException` - State transition blocked by guard
- `ImmutableTagDefinitionException` - Cannot modify enum-backed definition

### Testing

Uses Pest with Orchestra Testbench. Tests run against SQLite in-memory.

```
tests/
├── Feature/              # Integration tests
│   ├── DirectTaggingTest.php
│   ├── CategoryTaggingTest.php
│   ├── TagDefinitionTest.php
│   ├── MagicAttributesTest.php
│   ├── TransitionGuardsTest.php
│   ├── TenantScopingTest.php
│   └── TagsTaggingTagsTest.php
├── Unit/                 # Unit tests
└── Fixtures/             # Test models and definitions
    ├── Models/
    └── Definitions/
```

### Key Patterns

**Category Tags**: Parent tag (non-assignable) with child value tags
```php
$status = Tag::createCategory('Status');
$status->addChildren(['pending', 'complete']);
```

**Tag Model uses HasTags**: Enables RBAC patterns (roles tagging permissions)
```php
$adminRole->tag(['users.create', 'users.delete']);
```

**Tenant Scoping**: Tags have optional tenant_id, resolved from model or auth user
