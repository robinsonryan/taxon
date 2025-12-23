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

# Check formatting without fixing
composer lint:check

# Run Rector refactoring
composer refactor

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
- Enum-backed, array-backed, or database-backed values
- Transition guards and lifecycle hooks
- Magic property accessors

### Key Components

- `HasTags` trait - Core tagging functionality for models
- `TagDefinition` - Base class for class-based tag definitions
- `Tag` model - Hierarchical tag storage (categories are parent tags)
- `TaxonManager` - Central coordination facade

### Directory Structure

```
src/
├── Concerns/           # Traits (HasDirectTags, HasCategoryTags, etc.)
├── Contracts/          # Interfaces
├── Exceptions/         # Custom exceptions
├── Models/Tag.php      # Tag Eloquent model
├── HasTags.php         # Main trait for models
├── TagDefinition.php   # Base class for definitions
└── TaxonServiceProvider.php
```

### Testing

Uses Pest with Orchestra Testbench. Tests run against SQLite in-memory.

- `tests/Feature/` - Integration tests
- `tests/Unit/` - Unit tests
- `tests/Fixtures/` - Test models and definitions
