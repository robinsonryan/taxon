# Taxon - Laravel Tagging Package Build Specification

## Overview

This document specifies the complete build plan for `taxon`, a flexible tagging system for Laravel that supports hierarchical tags, tenant scoping, and class-based tag definitions with transition guards.

**Target Audience:** Claude Code (AI agent executing the build)

**Development Approach:** Test-Driven Development (TDD) with atomic, verifiable steps

**Local Path:** `~/dev/php/packages/robinsonryan/taxon`

---

## Package Identity

- **Name:** `robinsonryan/taxon`
- **Namespace:** `RobinsonRyan\Taxon`
- **Minimum PHP:** 8.2
- **Laravel Support:** 11.x, 12.x
- **License:** MIT
- **GitHub:** `robinsonryan/taxon`

---

## Core Concepts

### Tier 1: Convention-Based Tagging
- Direct tagging: `$model->tag('idea')`
- Categorized tagging: `$model->setTag('status', 'pending')`
- No configuration required
- Tags auto-created on first use

### Tier 2: Class-Based Tag Definitions
- Enum-backed values (immutable)
- Array-backed values (immutable)
- Database-backed values (admin-editable)
- Transition guards and lifecycle hooks
- Magic property accessors

### Magic Attribute Access
- Declare `$tagAttributes` array on model
- Access tags as properties: `$model->status`
- Set tags as properties: `$model->status = 'pending'`
- Supports both string categories and TagDefinition classes

### Tags Can Tag Tags
- Role → Permission mapping
- Hierarchical taxonomies
- Category → Value relationships

---

## Development Environment

### Required Tools
```bash
# Install globally or ensure available
composer global require phpunit/phpunit
composer global require pestphp/pest
composer global require laravel/pint
composer global require phpstan/phpstan
composer global require rector/rector
```

### Testing Framework
- **Pest** for tests (PHPUnit compatible)
- **Orchestra Testbench** for Laravel testing outside an app
- **SQLite in-memory** for fast test execution (with PostgreSQL/MySQL compatibility verified)

---

## Directory Structure

```
taxon/
├── .github/
│   └── workflows/
│       └── tests.yml
├── config/
│   └── taxon.php
├── database/
│   └── migrations/
│       └── 2024_01_01_000000_create_tags_tables.php
├── docs/
│   ├── README.md
│   ├── installation.md
│   ├── basic-usage.md
│   ├── categories.md
│   ├── tag-definitions.md
│   ├── magic-attributes.md
│   ├── tenant-scoping.md
│   └── api-reference.md
├── src/
│   ├── Builders/
│   │   └── TagBuilder.php
│   ├── Concerns/
│   │   ├── ConfiguresIdentifiers.php
│   │   ├── HasDirectTags.php
│   │   ├── HasCategoryTags.php
│   │   ├── HasTagAttributes.php
│   │   └── ResolveTenant.php
│   ├── Contracts/
│   │   ├── Taggable.php
│   │   └── TagDefinitionContract.php
│   ├── Exceptions/
│   │   ├── TagNotFoundException.php
│   │   ├── TagInUseException.php
│   │   ├── InvalidTransitionException.php
│   │   ├── ImmutableTagDefinitionException.php
│   │   └── InvalidTagValueException.php
│   ├── Facades/
│   │   └── Taxon.php
│   ├── Models/
│   │   └── Tag.php
│   ├── Scopes/
│   │   └── TenantScope.php
│   ├── HasTags.php
│   ├── TagDefinition.php
│   ├── TaxonManager.php
│   └── TaxonServiceProvider.php
├── tests/
│   ├── Fixtures/
│   │   ├── Models/
│   │   │   ├── TestModel.php
│   │   │   ├── TestUser.php
│   │   │   └── TestTenantModel.php
│   │   └── Definitions/
│   │       ├── StatusDefinition.php
│   │       ├── PriorityDefinition.php
│   │       └── RolesDefinition.php
│   ├── Feature/
│   │   ├── DirectTaggingTest.php
│   │   ├── CategoryTaggingTest.php
│   │   ├── TagHierarchyTest.php
│   │   ├── TagDefinitionTest.php
│   │   ├── MagicAttributesTest.php
│   │   ├── TransitionGuardsTest.php
│   │   ├── TenantScopingTest.php
│   │   ├── Uuid7Test.php
│   │   ├── QueryScopesTest.php
│   │   └── TagsTaggingTagsTest.php
│   ├── Unit/
│   │   ├── TagModelTest.php
│   │   ├── TagBuilderTest.php
│   │   └── TenantResolverTest.php
│   ├── Pest.php
│   └── TestCase.php
├── .gitignore
├── composer.json
├── LICENSE.md
├── pint.json
├── phpstan.neon
├── rector.php
├── README.md
└── CHANGELOG.md
```

---

## Build Phases

The build is divided into 10 phases. Each phase contains atomic steps. Complete all tests in a phase before moving to the next.

---

# PHASE 1: Project Scaffolding

## Step 1.0: Initialize DDEV Environment

**Action:** Create DDEV configuration for local development

**.ddev/config.yaml:**
```yaml
name: taxon
type: php
docroot: ""
php_version: "8.5"
webserver_type: nginx-fpm
database:
  type: postgres
  version: "18"
nodejs_version: "22"

hooks:
  post-start:
    - exec: composer install
```

**.ddev/commands/host/test:**
```bash
#!/bin/bash

## Description: Run test suite
## Usage: test
## Example: ddev test

ddev exec composer test "$@"
```

**.ddev/commands/host/quality:**
```bash
#!/bin/bash

## Description: Run full quality checks (lint, analyze, test)
## Usage: quality
## Example: ddev quality

ddev exec composer quality
```

**Commands to initialize:**
```bash
cd ~/dev/php/packages/robinsonryan/taxon
ddev start
ddev composer install
```

**Validation:** `ddev describe` shows running container

**Note:** Tests use SQLite in-memory via Orchestra Testbench, not the DDEV database.

---

## Step 1.1: Initialize Package Structure

**Action:** Create the base directory structure and composer.json

**Files to create:**
- `composer.json`
- `.gitignore`
- `LICENSE.md`
- `README.md`
- `CHANGELOG.md`

**composer.json:**
```json
{
    "name": "robinsonryan/taxon",
    "description": "Flexible hierarchical tagging system for Laravel with tenant scoping and tag definitions",
    "type": "library",
    "license": "MIT",
    "authors": [
        {
            "name": "Ryan Robinson",
            "email": "ryan@example.com"
        }
    ],
    "require": {
        "php": "^8.2",
        "illuminate/contracts": "^11.0|^12.0",
        "illuminate/database": "^11.0|^12.0",
        "illuminate/support": "^11.0|^12.0"
    },
    "require-dev": {
        "larastan/larastan": "^2.9",
        "laravel/pint": "^1.18",
        "orchestra/testbench": "^9.0|^10.0",
        "pestphp/pest": "^3.0",
        "pestphp/pest-plugin-laravel": "^3.0",
        "rector/rector": "^1.2"
    },
    "autoload": {
        "psr-4": {
            "RobinsonRyan\\Taxon\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "RobinsonRyan\\Taxon\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "RobinsonRyan\\Taxon\\TaxonServiceProvider"
            ]
        }
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true,
    "scripts": {
        "test": "pest",
        "test:coverage": "pest --coverage",
        "lint": "pint",
        "lint:check": "pint --test",
        "analyze": "phpstan analyse",
        "refactor": "rector process",
        "refactor:check": "rector process --dry-run",
        "quality": [
            "@lint:check",
            "@analyze",
            "@test"
        ]
    }
}
```

**.gitignore:**
```
/vendor/
/node_modules/
.env
.phpunit.result.cache
.php-cs-fixer.cache
composer.lock
phpstan.neon.cache
.idea/
.vscode/
*.swp
.DS_Store
coverage/
```

**Validation:** Run `composer validate`

---

## Step 1.2: Configure Development Tools

**Action:** Create configuration files for Pint, PHPStan, and Rector

**pint.json:**
```json
{
    "preset": "laravel",
    "rules": {
        "blank_line_before_statement": {
            "statements": ["return", "throw", "try"]
        },
        "concat_space": {
            "spacing": "one"
        },
        "method_argument_space": {
            "on_multiline": "ensure_fully_multiline"
        },
        "ordered_imports": {
            "sort_algorithm": "alpha"
        },
        "single_trait_insert_per_statement": true
    }
}
```

**phpstan.neon:**
```neon
includes:
    - vendor/larastan/larastan/extension.neon

parameters:
    paths:
        - src
    level: 8
    ignoreErrors: []
    checkMissingIterableValueType: false
```

**rector.php:**
```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_82,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
    ])
    ->withSkip([
        __DIR__ . '/vendor',
    ]);
```

**Validation:** Files created successfully

---

## Step 1.3: Setup Testing Infrastructure

**Action:** Create test configuration and base TestCase

**tests/Pest.php:**
```php
<?php

use RobinsonRyan\Taxon\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');
```

**tests/TestCase.php:**
```php
<?php

namespace RobinsonRyan\Taxon\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RobinsonRyan\Taxon\TaxonServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->setUpDatabase();
    }

    protected function getPackageProviders($app): array
    {
        return [
            TaxonServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        
        // Default to incrementing IDs
        $app['config']->set('taxon.id_type', 'incrementing');
    }

    protected function setUpDatabase(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        
        $this->createTestTables();
    }

    protected function createTestTables(): void
    {
        $useUuid = config('taxon.id_type') === 'uuid7';
        
        Schema::create('test_models', function (Blueprint $table) use ($useUuid) {
            if ($useUuid) {
                $table->uuid('id')->primary();
            } else {
                $table->id();
            }
            $table->string('name');
            $table->string('account_id')->nullable();
            $table->timestamps();
        });
        
        Schema::create('test_users', function (Blueprint $table) use ($useUuid) {
            if ($useUuid) {
                $table->uuid('id')->primary();
            } else {
                $table->id();
            }
            $table->string('name');
            $table->string('email');
            $table->string('account_id')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });
    }
    
    /**
     * Helper to run tests with UUID7 configuration
     */
    protected function useUuid7(): void
    {
        config()->set('taxon.id_type', 'uuid7');
        
        // Recreate tables with UUID columns
        Schema::dropIfExists('test_users');
        Schema::dropIfExists('test_models');
        Schema::dropIfExists(config('taxon.tables.taggables'));
        Schema::dropIfExists(config('taxon.tables.tags'));
        
        $this->setUpDatabase();
    }
}
```

**tests/Fixtures/Models/TestModel.php:**
```php
<?php

namespace RobinsonRyan\Taxon\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\Taxon\Concerns\ConfiguresIdentifiers;
use RobinsonRyan\Taxon\HasTags;

class TestModel extends Model
{
    use HasTags;
    use ConfiguresIdentifiers;
    
    protected $guarded = [];
}
```

**tests/Fixtures/Models/TestUser.php:**
```php
<?php

namespace RobinsonRyan\Taxon\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\Taxon\Concerns\ConfiguresIdentifiers;
use RobinsonRyan\Taxon\HasTags;

class TestUser extends Model
{
    use HasTags;
    use ConfiguresIdentifiers;
    
    protected $guarded = [];
    
    public function isAdmin(): bool
    {
        return $this->is_admin;
    }
}
```

**Validation:** Run `composer install` then `composer test` (tests will fail initially - that's expected in TDD)

---

## Step 1.4: Create Minimal Service Provider

**Action:** Create a minimal service provider that loads config and migrations

**src/TaxonServiceProvider.php:**
```php
<?php

namespace RobinsonRyan\Taxon;

use Illuminate\Support\ServiceProvider;

class TaxonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/taxon.php',
            'taxon'
        );
    }

    public function boot(): void
    {
        $this->publishConfig();
        $this->publishMigrations();
    }

    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/taxon.php' => config_path('taxon.php'),
        ], 'taxon-config');
    }

    protected function publishMigrations(): void
    {
        $this->publishesMigrations([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'taxon-migrations');
    }
}
```

**config/taxon.php:**
```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    */
    'tables' => [
        'tags' => 'tags',
        'taggables' => 'taggables',
    ],

    /*
    |--------------------------------------------------------------------------
    | Primary Key Type
    |--------------------------------------------------------------------------
    |
    | Supported: "incrementing", "uuid7"
    |
    */
    'id_type' => 'incrementing',

    /*
    |--------------------------------------------------------------------------
    | Tag Model
    |--------------------------------------------------------------------------
    */
    'tag_model' => RobinsonRyan\Taxon\Models\Tag::class,

    /*
    |--------------------------------------------------------------------------
    | Tenant Configuration
    |--------------------------------------------------------------------------
    */
    'tenant' => [
        'enabled' => false,
        'column' => 'tenant_id',
        'resolver' => 'auth',
        'auth_attribute' => 'account_id',
        'callback' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-create Tags
    |--------------------------------------------------------------------------
    */
    'auto_create' => true,

    /*
    |--------------------------------------------------------------------------
    | Morph Map
    |--------------------------------------------------------------------------
    */
    'morph_map' => [],
];
```

**Validation:** Service provider instantiates without error

---

## Step 1.5: Create Migration

**Action:** Create the tags and taggables migration

**database/migrations/2024_01_01_000000_create_tags_tables.php:**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tagTable = config('taxon.tables.tags', 'tags');
        $pivotTable = config('taxon.tables.taggables', 'taggables');
        $tenantColumn = config('taxon.tenant.column', 'tenant_id');
        $useUuid = config('taxon.id_type') === 'uuid7';

        Schema::create($tagTable, function (Blueprint $table) use ($tagTable, $tenantColumn, $useUuid) {
            if ($useUuid) {
                $table->uuid('id')->primary();
            } else {
                $table->id();
            }
            
            $table->string('name');
            $table->string('slug');
            
            if ($useUuid) {
                $table->uuid('parent_id')->nullable();
                $table->foreign('parent_id')
                    ->references('id')
                    ->on($tagTable)
                    ->cascadeOnDelete();
            } else {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->constrained($tagTable)
                    ->cascadeOnDelete();
            }
            
            $table->string($tenantColumn)->nullable()->index();
            $table->boolean('assignable')->default(true);
            $table->boolean('single_select')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['slug', 'parent_id', $tenantColumn], 'tags_unique_slug_parent_tenant');
        });

        Schema::create($pivotTable, function (Blueprint $table) use ($tagTable, $tenantColumn, $useUuid) {
            if ($useUuid) {
                $table->uuid('id')->primary();
                $table->uuid('tag_id');
                $table->foreign('tag_id')
                    ->references('id')
                    ->on($tagTable)
                    ->cascadeOnDelete();
            } else {
                $table->id();
                $table->foreignId('tag_id')
                    ->constrained($tagTable)
                    ->cascadeOnDelete();
            }
            
            $table->uuidMorphs('taggable');
            $table->string($tenantColumn)->nullable()->index();
            $table->timestamps();

            $table->unique(
                ['tag_id', 'taggable_type', 'taggable_id', $tenantColumn],
                'taggables_unique_tag_model_tenant'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('taxon.tables.taggables', 'taggables'));
        Schema::dropIfExists(config('taxon.tables.tags', 'tags'));
    }
};
```

**Validation:** Migration runs successfully in test environment

---

# PHASE 2: Tag Model & Basic Operations

## Step 2.1: Write Tag Model Tests

**Action:** Write tests for the Tag model BEFORE implementing

**tests/Unit/TagModelTest.php:**
```php
<?php

use RobinsonRyan\Taxon\Models\Tag;

describe('Tag Model', function () {
    it('can be created with name and slug', function () {
        $tag = Tag::create([
            'name' => 'Test Tag',
            'slug' => 'test-tag',
        ]);
        
        expect($tag)->toBeInstanceOf(Tag::class)
            ->and($tag->name)->toBe('Test Tag')
            ->and($tag->slug)->toBe('test-tag');
    });
    
    it('auto-generates slug from name if not provided', function () {
        $tag = Tag::create(['name' => 'My Awesome Tag']);
        
        expect($tag->slug)->toBe('my-awesome-tag');
    });
    
    it('has default values for assignable and single_select', function () {
        $tag = Tag::create(['name' => 'Test']);
        
        expect($tag->assignable)->toBeTrue()
            ->and($tag->single_select)->toBeTrue();
    });
    
    it('can have a parent tag', function () {
        $parent = Tag::create(['name' => 'Status']);
        $child = Tag::create([
            'name' => 'Pending',
            'parent_id' => $parent->id,
        ]);
        
        expect($child->parent->id)->toBe($parent->id);
    });
    
    it('can have children tags', function () {
        $parent = Tag::create(['name' => 'Status']);
        Tag::create(['name' => 'Pending', 'parent_id' => $parent->id]);
        Tag::create(['name' => 'Complete', 'parent_id' => $parent->id]);
        
        expect($parent->children)->toHaveCount(2);
    });
    
    it('is a root tag when parent_id is null', function () {
        $root = Tag::create(['name' => 'Status']);
        $child = Tag::create(['name' => 'Pending', 'parent_id' => $root->id]);
        
        expect($root->isRoot())->toBeTrue()
            ->and($child->isRoot())->toBeFalse();
    });
    
    it('is a category when it has children', function () {
        $category = Tag::create(['name' => 'Status']);
        Tag::create(['name' => 'Pending', 'parent_id' => $category->id]);
        
        $loneTag = Tag::create(['name' => 'Ideas']);
        
        expect($category->fresh()->isCategory())->toBeTrue()
            ->and($loneTag->isCategory())->toBeFalse();
    });
    
    it('casts meta to array', function () {
        $tag = Tag::create([
            'name' => 'Test',
            'meta' => ['color' => 'red', 'icon' => 'star'],
        ]);
        
        expect($tag->meta)->toBeArray()
            ->and($tag->meta['color'])->toBe('red');
    });
    
    it('cascades delete to children', function () {
        $parent = Tag::create(['name' => 'Status']);
        $child1 = Tag::create(['name' => 'Pending', 'parent_id' => $parent->id]);
        $child2 = Tag::create(['name' => 'Complete', 'parent_id' => $parent->id]);
        
        $parent->delete();
        
        expect(Tag::find($child1->id))->toBeNull()
            ->and(Tag::find($child2->id))->toBeNull();
    });
});

describe('Tag Query Scopes', function () {
    beforeEach(function () {
        $this->status = Tag::create(['name' => 'Status', 'assignable' => false]);
        Tag::create(['name' => 'Pending', 'parent_id' => $this->status->id]);
        Tag::create(['name' => 'Complete', 'parent_id' => $this->status->id]);
        $this->ideas = Tag::create(['name' => 'Ideas']);
    });
    
    it('scopes to root tags', function () {
        $roots = Tag::roots()->get();
        
        expect($roots)->toHaveCount(2)
            ->and($roots->pluck('slug')->toArray())->toContain('status', 'ideas');
    });
    
    it('scopes to categories', function () {
        $categories = Tag::categories()->get();
        
        expect($categories)->toHaveCount(1)
            ->and($categories->first()->slug)->toBe('status');
    });
    
    it('scopes to assignable tags', function () {
        $assignable = Tag::assignable()->get();
        
        expect($assignable)->toHaveCount(3); // pending, complete, ideas
    });
    
    it('scopes by slug', function () {
        $tag = Tag::slug('pending')->first();
        
        expect($tag->name)->toBe('Pending');
    });
    
    it('scopes children of a category', function () {
        $children = Tag::childrenOf('status')->get();
        
        expect($children)->toHaveCount(2);
    });
});
```

**Validation:** Tests fail (RED phase of TDD)

---

## Step 2.2: Implement Tag Model

**Action:** Implement the Tag model to make tests pass

**src/Models/Tag.php:**
```php
<?php

namespace RobinsonRyan\Taxon\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;
use RobinsonRyan\Taxon\Concerns\ConfiguresIdentifiers;
use RobinsonRyan\Taxon\Exceptions\TagInUseException;
use RobinsonRyan\Taxon\HasTags;

class Tag extends Model
{
    use HasTags;
    use ConfiguresIdentifiers;

    protected $guarded = [];

    protected $casts = [
        'assignable' => 'boolean',
        'single_select' => 'boolean',
        'meta' => 'array',
    ];

    protected $attributes = [
        'assignable' => true,
        'single_select' => true,
    ];

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::creating(function (Tag $tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Table Configuration
    |--------------------------------------------------------------------------
    */

    public function getTable(): string
    {
        return config('taxon.tables.tags', 'tags');
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id');
    }

    public function taggables(string $type = null): MorphToMany
    {
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        $query = $this->morphedByMany(
            $type ?? Model::class,
            'taggable',
            $pivotTable
        );

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Helpers
    |--------------------------------------------------------------------------
    */

    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    public function isCategory(): bool
    {
        return $this->children()->exists();
    }

    public function isLeaf(): bool
    {
        return ! $this->isCategory();
    }

    public function isAssignable(): bool
    {
        return $this->assignable;
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeCategories(Builder $query): Builder
    {
        return $query->whereHas('children');
    }

    public function scopeAssignable(Builder $query): Builder
    {
        return $query->where('assignable', true);
    }

    public function scopeSlug(Builder $query, string $slug): Builder
    {
        return $query->where('slug', $slug);
    }

    public function scopeChildrenOf(Builder $query, string|int $parent): Builder
    {
        if (is_string($parent)) {
            $parent = static::where('slug', $parent)->value('id');
        }

        return $query->where('parent_id', $parent);
    }

    /*
    |--------------------------------------------------------------------------
    | Factory Methods
    |--------------------------------------------------------------------------
    */

    public static function createCategory(
        string $name,
        ?string $tenantId = null,
        bool $singleSelect = true,
        ?string $slug = null,
    ): static {
        return static::create([
            'name' => $name,
            'slug' => $slug ?? Str::slug($name),
            'parent_id' => null,
            'tenant_id' => $tenantId,
            'assignable' => false,
            'single_select' => $singleSelect,
        ]);
    }

    public static function createValue(
        string $name,
        string|int $parentId,
        ?string $tenantId = null,
        ?string $slug = null,
    ): static {
        return static::create([
            'name' => $name,
            'slug' => $slug ?? Str::slug($name),
            'parent_id' => $parentId,
            'tenant_id' => $tenantId,
            'assignable' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Child Management
    |--------------------------------------------------------------------------
    */

    public function addChild(string $name, ?string $slug = null): static
    {
        return static::createValue(
            name: $name,
            parentId: $this->id,
            tenantId: $this->tenant_id,
            slug: $slug,
        );
    }

    public function addChildren(array $names): \Illuminate\Support\Collection
    {
        return collect($names)->map(fn ($name) => $this->addChild($name));
    }

    public function syncChildren(array $values): \Illuminate\Support\Collection
    {
        $keepIds = [];

        foreach ($values as $value) {
            if (isset($value['id'])) {
                $this->children()->where('id', $value['id'])->update([
                    'name' => $value['name'],
                    'slug' => Str::slug($value['name']),
                ]);
                $keepIds[] = $value['id'];
            } else {
                $child = $this->addChild($value['name']);
                $keepIds[] = $child->id;
            }
        }

        $this->children()->whereNotIn('id', $keepIds)->delete();

        return $this->children()->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Deletion
    |--------------------------------------------------------------------------
    */

    public function destroy(): bool
    {
        $this->assertNotInUse();

        return $this->delete();
    }

    public function forceDestroy(): bool
    {
        return $this->delete();
    }

    protected function assertNotInUse(): void
    {
        if ($this->taggablesCount() > 0) {
            throw new TagInUseException($this);
        }

        $this->children->each(fn (Tag $child) => $child->assertNotInUse());
    }

    public function taggablesCount(): int
    {
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        return \DB::table($pivotTable)
            ->where('tag_id', $this->id)
            ->count();
    }

    public function totalTaggablesCount(): int
    {
        return $this->taggablesCount() +
            $this->children->sum(fn (Tag $child) => $child->totalTaggablesCount());
    }
}
```

**src/Concerns/ConfiguresIdentifiers.php:**
```php
<?php

namespace RobinsonRyan\Taxon\Concerns;

use Illuminate\Support\Str;

trait ConfiguresIdentifiers
{
    public function getIncrementing(): bool
    {
        return config('taxon.id_type') !== 'uuid7';
    }

    public function getKeyType(): string
    {
        return config('taxon.id_type') === 'uuid7' ? 'string' : 'int';
    }

    protected static function bootConfiguresIdentifiers(): void
    {
        if (config('taxon.id_type') === 'uuid7') {
            static::creating(function ($model) {
                if (empty($model->{$model->getKeyName()})) {
                    $model->{$model->getKeyName()} = Str::uuid7()->toString();
                }
            });
        }
    }
}
```

**src/Exceptions/TagInUseException.php:**
```php
<?php

namespace RobinsonRyan\Taxon\Exceptions;

use Exception;
use RobinsonRyan\Taxon\Models\Tag;

class TagInUseException extends Exception
{
    public function __construct(
        public readonly Tag $tag
    ) {
        $count = $tag->taggablesCount();

        parent::__construct(
            "Cannot delete tag '{$tag->name}': currently assigned to {$count} model(s)."
        );
    }
}
```

**Validation:** Run `composer test tests/Unit/TagModelTest.php` - all tests pass (GREEN)

---

## Step 2.3: Refactor Tag Model

**Action:** Run Pint and PHPStan, fix any issues

```bash
composer lint
composer analyze
```

**Validation:** No linting or static analysis errors

---

# PHASE 3: Direct Tagging (HasTags Trait)

## Step 3.1: Write Direct Tagging Tests

**Action:** Write tests for basic direct tagging functionality

**tests/Feature/DirectTaggingTest.php:**
```php
<?php

use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;

describe('Direct Tagging', function () {
    beforeEach(function () {
        $this->model = TestModel::create(['name' => 'Test']);
    });

    it('can tag a model with a string', function () {
        $this->model->tag('important');
        
        expect($this->model->tags)->toHaveCount(1)
            ->and($this->model->tags->first()->slug)->toBe('important');
    });

    it('auto-creates tag if it does not exist', function () {
        expect(Tag::where('slug', 'new-tag')->exists())->toBeFalse();
        
        $this->model->tag('new-tag');
        
        expect(Tag::where('slug', 'new-tag')->exists())->toBeTrue();
    });

    it('can tag with multiple strings', function () {
        $this->model->tag(['idea', 'todo', 'urgent']);
        
        expect($this->model->tags)->toHaveCount(3);
    });

    it('does not duplicate tags', function () {
        $this->model->tag('important');
        $this->model->tag('important');
        
        expect($this->model->tags)->toHaveCount(1);
    });

    it('can untag a model', function () {
        $this->model->tag(['a', 'b', 'c']);
        $this->model->untag('b');
        
        expect($this->model->tags)->toHaveCount(2)
            ->and($this->model->tags->pluck('slug')->toArray())->not->toContain('b');
    });

    it('can untag multiple', function () {
        $this->model->tag(['a', 'b', 'c']);
        $this->model->untag(['a', 'c']);
        
        expect($this->model->tags)->toHaveCount(1)
            ->and($this->model->tags->first()->slug)->toBe('b');
    });

    it('can retag (sync) a model', function () {
        $this->model->tag(['a', 'b', 'c']);
        $this->model->retag(['x', 'y']);
        
        expect($this->model->tags)->toHaveCount(2)
            ->and($this->model->tags->pluck('slug')->toArray())->toBe(['x', 'y']);
    });

    it('can detach all tags', function () {
        $this->model->tag(['a', 'b', 'c']);
        $this->model->detachAllTags();
        
        expect($this->model->tags)->toHaveCount(0);
    });
});

describe('Direct Tag Checks', function () {
    beforeEach(function () {
        $this->model = TestModel::create(['name' => 'Test']);
        $this->model->tag(['alpha', 'beta', 'gamma']);
    });

    it('checks if model has a tag', function () {
        expect($this->model->hasTag('alpha'))->toBeTrue()
            ->and($this->model->hasTag('delta'))->toBeFalse();
    });

    it('checks if model has any of given tags', function () {
        expect($this->model->hasAnyTag(['alpha', 'delta']))->toBeTrue()
            ->and($this->model->hasAnyTag(['delta', 'epsilon']))->toBeFalse();
    });

    it('checks if model has all given tags', function () {
        expect($this->model->hasAllTags(['alpha', 'beta']))->toBeTrue()
            ->and($this->model->hasAllTags(['alpha', 'delta']))->toBeFalse();
    });
});

describe('Direct Tag Query Scopes', function () {
    beforeEach(function () {
        $this->m1 = TestModel::create(['name' => 'M1']);
        $this->m2 = TestModel::create(['name' => 'M2']);
        $this->m3 = TestModel::create(['name' => 'M3']);
        
        $this->m1->tag(['php', 'laravel']);
        $this->m2->tag(['php', 'vue']);
        $this->m3->tag(['javascript', 'vue']);
    });

    it('scopes models with a specific tag', function () {
        $models = TestModel::withTag('php')->get();
        
        expect($models)->toHaveCount(2)
            ->and($models->pluck('name')->toArray())->toContain('M1', 'M2');
    });

    it('scopes models with any of given tags', function () {
        $models = TestModel::withAnyTag(['laravel', 'vue'])->get();
        
        expect($models)->toHaveCount(3);
    });

    it('scopes models with all given tags', function () {
        $models = TestModel::withAllTags(['php', 'vue'])->get();
        
        expect($models)->toHaveCount(1)
            ->and($models->first()->name)->toBe('M2');
    });

    it('scopes models without a specific tag', function () {
        $models = TestModel::withoutTag('php')->get();
        
        expect($models)->toHaveCount(1)
            ->and($models->first()->name)->toBe('M3');
    });
});
```

**Validation:** Tests fail (RED)

---

## Step 3.2: Implement HasTags Trait (Direct Tagging)

**Action:** Implement the direct tagging portion of HasTags

**src/HasTags.php:**
```php
<?php

namespace RobinsonRyan\Taxon;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RobinsonRyan\Taxon\Models\Tag;

trait HasTags
{
    /*
    |--------------------------------------------------------------------------
    | Magic Attribute Access
    |--------------------------------------------------------------------------
    */

    public function getAttribute($key)
    {
        // Check if this key is a declared tag attribute
        if ($this->isTagAttribute($key)) {
            return $this->getTagAttributeValue($key);
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        // Check if this key is a declared tag attribute
        if ($this->isTagAttribute($key)) {
            $this->setTagAttributeValue($key, $value);
            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    protected function isTagAttribute(string $key): bool
    {
        if (! property_exists($this, 'tagAttributes')) {
            return false;
        }

        // Supports both indexed array ['status'] and associative ['status' => Definition::class]
        return array_key_exists($key, $this->tagAttributes) 
            || in_array($key, $this->tagAttributes, true);
    }

    protected function getTagAttributeValue(string $key): mixed
    {
        $definition = $this->getTagAttributeDefinition($key);

        if ($definition !== null) {
            return $this->getTagAs($definition);
        }

        return $this->getTagValueIn($key);
    }

    protected function setTagAttributeValue(string $key, mixed $value): void
    {
        $definition = $this->getTagAttributeDefinition($key);

        if ($definition !== null) {
            $this->setTagAs($definition, $value);
            return;
        }

        $this->setTag($key, $value);
    }

    protected function getTagAttributeDefinition(string $key): ?string
    {
        if (! property_exists($this, 'tagAttributes')) {
            return null;
        }

        // If associative with class value
        if (array_key_exists($key, $this->tagAttributes)) {
            $value = $this->tagAttributes[$key];
            if (is_string($value) && class_exists($value)) {
                return $value;
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function tags(): MorphToMany
    {
        $pivotTable = config('taxon.tables.taggables', 'taggables');

        return $this->morphToMany(
            config('taxon.tag_model', Tag::class),
            'taggable',
            $pivotTable
        )->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Direct Tagging Methods
    |--------------------------------------------------------------------------
    */

    public function tag(string|array $tags): static
    {
        $tags = Arr::wrap($tags);
        $tagModels = $this->resolveOrCreateTags($tags);

        $this->tags()->syncWithoutDetaching($tagModels->pluck('id'));
        $this->load('tags');

        return $this;
    }

    public function untag(string|array $tags): static
    {
        $tags = Arr::wrap($tags);
        $tagIds = $this->resolveTags($tags)->pluck('id');

        $this->tags()->detach($tagIds);
        $this->load('tags');

        return $this;
    }

    public function retag(array $tags): static
    {
        $tagModels = $this->resolveOrCreateTags($tags);

        $this->tags()->sync($tagModels->pluck('id'));
        $this->load('tags');

        return $this;
    }

    public function detachAllTags(): static
    {
        $this->tags()->detach();
        $this->load('tags');

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Direct Tag Checks
    |--------------------------------------------------------------------------
    */

    public function hasTag(string $tag): bool
    {
        return $this->tags->contains(function (Tag $t) use ($tag) {
            return $t->slug === Str::slug($tag);
        });
    }

    public function hasAnyTag(array $tags): bool
    {
        $slugs = collect($tags)->map(fn ($t) => Str::slug($t));

        return $this->tags->contains(function (Tag $t) use ($slugs) {
            return $slugs->contains($t->slug);
        });
    }

    public function hasAllTags(array $tags): bool
    {
        $slugs = collect($tags)->map(fn ($t) => Str::slug($t));
        $modelSlugs = $this->tags->pluck('slug');

        return $slugs->every(fn ($slug) => $modelSlugs->contains($slug));
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeWithTag(Builder $query, string $tag): Builder
    {
        $slug = Str::slug($tag);

        return $query->whereHas('tags', function (Builder $q) use ($slug) {
            $q->where('slug', $slug);
        });
    }

    public function scopeWithAnyTag(Builder $query, array $tags): Builder
    {
        $slugs = collect($tags)->map(fn ($t) => Str::slug($t));

        return $query->whereHas('tags', function (Builder $q) use ($slugs) {
            $q->whereIn('slug', $slugs);
        });
    }

    public function scopeWithAllTags(Builder $query, array $tags): Builder
    {
        $slugs = collect($tags)->map(fn ($t) => Str::slug($t));

        foreach ($slugs as $slug) {
            $query->whereHas('tags', function (Builder $q) use ($slug) {
                $q->where('slug', $slug);
            });
        }

        return $query;
    }

    public function scopeWithoutTag(Builder $query, string $tag): Builder
    {
        $slug = Str::slug($tag);

        return $query->whereDoesntHave('tags', function (Builder $q) use ($slug) {
            $q->where('slug', $slug);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Resolution Helpers
    |--------------------------------------------------------------------------
    */

    protected function resolveOrCreateTags(array $tags): Collection
    {
        return collect($tags)->map(function ($tag) {
            $slug = Str::slug($tag);

            return Tag::firstOrCreate(
                ['slug' => $slug, 'parent_id' => null],
                ['name' => $tag]
            );
        });
    }

    protected function resolveTags(array $tags): Collection
    {
        $slugs = collect($tags)->map(fn ($t) => Str::slug($t));

        return Tag::whereIn('slug', $slugs)->whereNull('parent_id')->get();
    }
}
```

**Validation:** Run `composer test tests/Feature/DirectTaggingTest.php` - all tests pass (GREEN)

---

## Step 3.3: Refactor Direct Tagging

**Action:** Run quality checks

```bash
composer lint
composer analyze
```

**Validation:** No errors

---

# PHASE 4: Category Tagging

## Step 4.1: Write Category Tagging Tests

**Action:** Write tests for setTag/addTag category operations

**tests/Feature/CategoryTaggingTest.php:**
```php
<?php

use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;

beforeEach(function () {
    // Create status category
    $this->status = Tag::createCategory('Status', singleSelect: true);
    $this->status->addChildren(['pending', 'in-review', 'complete']);
    
    // Create equipment category (multi-select)
    $this->equipment = Tag::createCategory('Equipment', singleSelect: false);
    $this->equipment->addChildren(['weights', 'treadmill', 'bosu']);
    
    $this->model = TestModel::create(['name' => 'Test']);
});

describe('setTag (Single-Select)', function () {
    it('assigns a tag within a category', function () {
        $this->model->setTag('status', 'pending');
        
        expect($this->model->getTagIn('status'))->not->toBeNull()
            ->and($this->model->getTagIn('status')->slug)->toBe('pending');
    });

    it('replaces existing tag in category', function () {
        $this->model->setTag('status', 'pending');
        $this->model->setTag('status', 'complete');
        
        expect($this->model->tagsIn('status'))->toHaveCount(1)
            ->and($this->model->getTagIn('status')->slug)->toBe('complete');
    });

    it('returns the tag value as string', function () {
        $this->model->setTag('status', 'pending');
        
        expect($this->model->getTagValueIn('status'))->toBe('pending');
    });

    it('returns null if no tag in category', function () {
        expect($this->model->getTagIn('status'))->toBeNull()
            ->and($this->model->getTagValueIn('status'))->toBeNull();
    });
});

describe('addTag (Multi-Select)', function () {
    it('adds a tag within a category', function () {
        $this->model->addTag('equipment', 'weights');
        
        expect($this->model->tagsIn('equipment'))->toHaveCount(1);
    });

    it('accumulates tags in category', function () {
        $this->model->addTag('equipment', 'weights');
        $this->model->addTag('equipment', 'bosu');
        
        expect($this->model->tagsIn('equipment'))->toHaveCount(2)
            ->and($this->model->tagsIn('equipment')->pluck('slug')->toArray())
            ->toContain('weights', 'bosu');
    });

    it('can add multiple tags at once', function () {
        $this->model->addTags('equipment', ['weights', 'treadmill']);
        
        expect($this->model->tagsIn('equipment'))->toHaveCount(2);
    });

    it('does not duplicate tags', function () {
        $this->model->addTag('equipment', 'weights');
        $this->model->addTag('equipment', 'weights');
        
        expect($this->model->tagsIn('equipment'))->toHaveCount(1);
    });
});

describe('removeTag', function () {
    it('removes a specific tag from category', function () {
        $this->model->addTags('equipment', ['weights', 'bosu']);
        $this->model->removeTag('equipment', 'weights');
        
        expect($this->model->tagsIn('equipment'))->toHaveCount(1)
            ->and($this->model->tagsIn('equipment')->first()->slug)->toBe('bosu');
    });

    it('removes all tags from category when no value specified', function () {
        $this->model->addTags('equipment', ['weights', 'bosu']);
        $this->model->removeTagsIn('equipment');
        
        expect($this->model->tagsIn('equipment'))->toHaveCount(0);
    });
});

describe('hasTagIn Checks', function () {
    beforeEach(function () {
        $this->model->setTag('status', 'pending');
        $this->model->addTags('equipment', ['weights', 'bosu']);
    });

    it('checks if model has tag in category', function () {
        expect($this->model->hasTagIn('status', 'pending'))->toBeTrue()
            ->and($this->model->hasTagIn('status', 'complete'))->toBeFalse();
    });

    it('checks if model has any tag in category', function () {
        expect($this->model->hasAnyTagIn('equipment', ['weights', 'treadmill']))->toBeTrue()
            ->and($this->model->hasAnyTagIn('equipment', ['treadmill']))->toBeFalse();
    });

    it('checks if model has all tags in category', function () {
        expect($this->model->hasAllTagsIn('equipment', ['weights', 'bosu']))->toBeTrue()
            ->and($this->model->hasAllTagsIn('equipment', ['weights', 'treadmill']))->toBeFalse();
    });
});

describe('Category Query Scopes', function () {
    beforeEach(function () {
        $this->m1 = TestModel::create(['name' => 'M1']);
        $this->m2 = TestModel::create(['name' => 'M2']);
        $this->m3 = TestModel::create(['name' => 'M3']);
        
        $this->m1->setTag('status', 'pending');
        $this->m2->setTag('status', 'complete');
        $this->m3->setTag('status', 'pending');
    });

    it('scopes models with tag in category', function () {
        $models = TestModel::withTagIn('status', 'pending')->get();
        
        expect($models)->toHaveCount(2)
            ->and($models->pluck('name')->toArray())->toContain('M1', 'M3');
    });

    it('scopes models with any tag in category', function () {
        $models = TestModel::withAnyTagIn('status', ['pending', 'in-review'])->get();
        
        expect($models)->toHaveCount(2);
    });

    it('scopes models without tag in category', function () {
        $models = TestModel::withoutTagIn('status', 'pending')->get();
        
        expect($models)->toHaveCount(1)
            ->and($models->first()->name)->toBe('M2');
    });
});
```

**Validation:** Tests fail (RED)

---

## Step 4.2: Implement Category Tagging

**Action:** Add category tagging methods to HasTags trait

**Add to src/HasTags.php:** (append to existing trait)

```php
    /*
    |--------------------------------------------------------------------------
    | Category Tagging Methods
    |--------------------------------------------------------------------------
    */

    public function setTag(string $category, string $value): static
    {
        $categoryTag = $this->resolveCategoryTag($category);
        $valueTag = $this->resolveOrCreateValueTag($categoryTag, $value);

        // Remove existing tags in this category
        $this->removeTagsIn($category);

        // Attach the new value
        $this->tags()->attach($valueTag->id);
        $this->load('tags');

        return $this;
    }

    public function addTag(string $category, string $value): static
    {
        $categoryTag = $this->resolveCategoryTag($category);
        $valueTag = $this->resolveOrCreateValueTag($categoryTag, $value);

        $this->tags()->syncWithoutDetaching([$valueTag->id]);
        $this->load('tags');

        return $this;
    }

    public function addTags(string $category, array $values): static
    {
        foreach ($values as $value) {
            $this->addTag($category, $value);
        }

        return $this;
    }

    public function removeTag(string $category, string $value): static
    {
        $categoryTag = $this->resolveCategoryTag($category);
        $valueTag = Tag::where('slug', Str::slug($value))
            ->where('parent_id', $categoryTag->id)
            ->first();

        if ($valueTag) {
            $this->tags()->detach($valueTag->id);
            $this->load('tags');
        }

        return $this;
    }

    public function removeTagsIn(string $category): static
    {
        $categoryTag = Tag::where('slug', Str::slug($category))
            ->whereNull('parent_id')
            ->first();

        if (! $categoryTag) {
            return $this;
        }

        $valueTagIds = $categoryTag->children()->pluck('id');
        $this->tags()->detach($valueTagIds);
        $this->load('tags');

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Category Tag Accessors
    |--------------------------------------------------------------------------
    */

    public function tagsIn(string $category): Collection
    {
        $categoryTag = Tag::where('slug', Str::slug($category))
            ->whereNull('parent_id')
            ->first();

        if (! $categoryTag) {
            return new Collection();
        }

        return $this->tags->filter(
            fn (Tag $tag) => $tag->parent_id === $categoryTag->id
        )->values();
    }

    public function getTagIn(string $category): ?Tag
    {
        return $this->tagsIn($category)->first();
    }

    public function getTagValueIn(string $category): ?string
    {
        return $this->getTagIn($category)?->slug;
    }

    /*
    |--------------------------------------------------------------------------
    | Category Tag Checks
    |--------------------------------------------------------------------------
    */

    public function hasTagIn(string $category, string $value): bool
    {
        return $this->tagsIn($category)->contains(
            fn (Tag $tag) => $tag->slug === Str::slug($value)
        );
    }

    public function hasAnyTagIn(string $category, array $values): bool
    {
        $slugs = collect($values)->map(fn ($v) => Str::slug($v));
        $modelSlugs = $this->tagsIn($category)->pluck('slug');

        return $slugs->contains(fn ($slug) => $modelSlugs->contains($slug));
    }

    public function hasAllTagsIn(string $category, array $values): bool
    {
        $slugs = collect($values)->map(fn ($v) => Str::slug($v));
        $modelSlugs = $this->tagsIn($category)->pluck('slug');

        return $slugs->every(fn ($slug) => $modelSlugs->contains($slug));
    }

    /*
    |--------------------------------------------------------------------------
    | Category Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeWithTagIn(Builder $query, string $category, string $value): Builder
    {
        $categorySlug = Str::slug($category);
        $valueSlug = Str::slug($value);

        return $query->whereHas('tags', function (Builder $q) use ($categorySlug, $valueSlug) {
            $q->where('slug', $valueSlug)
                ->whereHas('parent', fn (Builder $p) => $p->where('slug', $categorySlug));
        });
    }

    public function scopeWithAnyTagIn(Builder $query, string $category, array $values): Builder
    {
        $categorySlug = Str::slug($category);
        $valueSlugs = collect($values)->map(fn ($v) => Str::slug($v));

        return $query->whereHas('tags', function (Builder $q) use ($categorySlug, $valueSlugs) {
            $q->whereIn('slug', $valueSlugs)
                ->whereHas('parent', fn (Builder $p) => $p->where('slug', $categorySlug));
        });
    }

    public function scopeWithoutTagIn(Builder $query, string $category, string $value): Builder
    {
        $categorySlug = Str::slug($category);
        $valueSlug = Str::slug($value);

        return $query->whereDoesntHave('tags', function (Builder $q) use ($categorySlug, $valueSlug) {
            $q->where('slug', $valueSlug)
                ->whereHas('parent', fn (Builder $p) => $p->where('slug', $categorySlug));
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Category Resolution Helpers
    |--------------------------------------------------------------------------
    */

    protected function resolveCategoryTag(string $category): Tag
    {
        $slug = Str::slug($category);

        $tag = Tag::where('slug', $slug)->whereNull('parent_id')->first();

        if (! $tag && config('taxon.auto_create', true)) {
            $tag = Tag::createCategory($category);
        }

        if (! $tag) {
            throw new \RobinsonRyan\Taxon\Exceptions\TagNotFoundException(
                "Category tag '{$category}' not found."
            );
        }

        return $tag;
    }

    protected function resolveOrCreateValueTag(Tag $category, string $value): Tag
    {
        $slug = Str::slug($value);

        return Tag::firstOrCreate(
            ['slug' => $slug, 'parent_id' => $category->id],
            ['name' => $value, 'assignable' => true]
        );
    }
```

**src/Exceptions/TagNotFoundException.php:**
```php
<?php

namespace RobinsonRyan\Taxon\Exceptions;

use Exception;

class TagNotFoundException extends Exception
{
    //
}
```

**Validation:** Run `composer test tests/Feature/CategoryTaggingTest.php` - all tests pass (GREEN)

---

# PHASE 5: Magic Attribute Access

## Step 5.1: Write Magic Attributes Tests

**Action:** Write tests for $tagAttributes magic property access

**tests/Fixtures/Models/TestModelWithAttributes.php:**
```php
<?php

namespace RobinsonRyan\Taxon\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\Taxon\HasTags;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusDefinition;

class TestModelWithAttributes extends Model
{
    use HasTags;
    
    protected $table = 'test_models';
    
    protected $guarded = [];
    
    protected array $tagAttributes = [
        'status',                              // string-based category
        'priority' => StatusDefinition::class, // definition-backed
    ];
}
```

**tests/Feature/MagicAttributesTest.php:**
```php
<?php

use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusEnum;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModelWithAttributes;

describe('Magic Attribute Access - String Categories', function () {
    beforeEach(function () {
        Tag::createCategory('Status', singleSelect: true)
            ->addChildren(['pending', 'complete', 'archived']);
            
        $this->model = TestModelWithAttributes::create(['name' => 'Test']);
    });

    it('can get tag value as property', function () {
        $this->model->setTag('status', 'pending');
        
        expect($this->model->status)->toBe('pending');
    });

    it('can set tag value as property', function () {
        $this->model->status = 'complete';
        
        expect($this->model->getTagValueIn('status'))->toBe('complete');
    });

    it('returns null when no tag set', function () {
        expect($this->model->status)->toBeNull();
    });

    it('replaces existing value on set', function () {
        $this->model->status = 'pending';
        $this->model->status = 'complete';
        
        expect($this->model->status)->toBe('complete')
            ->and($this->model->tagsIn('status'))->toHaveCount(1);
    });
});

describe('Magic Attribute Access - TagDefinition Backed', function () {
    beforeEach(function () {
        // Ensure the definition tag exists
        StatusDefinition::tag();
        StatusDefinition::valueTag(StatusEnum::PENDING);
        StatusDefinition::valueTag(StatusEnum::APPROVED);
        
        $this->model = TestModelWithAttributes::create(['name' => 'Test']);
    });

    it('can get typed enum value as property', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING);
        
        expect($this->model->priority)->toBe(StatusEnum::PENDING);
    });

    it('can set enum value as property', function () {
        $this->model->priority = StatusEnum::APPROVED;
        
        expect($this->model->getTagAs(StatusDefinition::class))->toBe(StatusEnum::APPROVED);
    });

    it('can set string value that maps to enum', function () {
        $this->model->priority = 'pending';
        
        expect($this->model->getTagAs(StatusDefinition::class))->toBe(StatusEnum::PENDING);
    });
});

describe('Magic Attributes - Non-Tag Attributes', function () {
    it('does not interfere with regular model attributes', function () {
        $model = TestModelWithAttributes::create(['name' => 'Original']);
        
        expect($model->name)->toBe('Original');
        
        $model->name = 'Updated';
        expect($model->name)->toBe('Updated');
    });

    it('does not interfere with model without tagAttributes', function () {
        $model = TestModel::create(['name' => 'Test']);
        
        expect($model->name)->toBe('Test');
        
        // This should not try to resolve as a tag
        expect($model->nonexistent ?? 'default')->toBe('default');
    });
});
```

**Validation:** Tests fail (RED)

---

## Step 5.2: Implement Magic Attribute Access

**Action:** The implementation is already included in the HasTags trait from Step 3.2. Verify tests pass.

**Validation:** Run `composer test tests/Feature/MagicAttributesTest.php` - all tests pass (GREEN)

---

# PHASE 6: Tag Definitions (Tier 2)

## Step 6.1: Write TagDefinition Tests

**Action:** Write tests for class-based tag definitions

**tests/Fixtures/Definitions/StatusDefinition.php:**
```php
<?php

namespace RobinsonRyan\Taxon\Tests\Fixtures\Definitions;

use RobinsonRyan\Taxon\TagDefinition;

enum StatusEnum: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
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

    public static function default(): StatusEnum
    {
        return StatusEnum::DRAFT;
    }
}
```

**tests/Fixtures/Definitions/PriorityDefinition.php:**
```php
<?php

namespace RobinsonRyan\Taxon\Tests\Fixtures\Definitions;

use RobinsonRyan\Taxon\TagDefinition;

class PriorityDefinition extends TagDefinition
{
    public static string $slug = 'priority';
    public static string $name = 'Priority';
    public static bool $singleSelect = true;
    public static bool $global = true;

    // Database-backed: no enum() or values() override
}
```

**tests/Feature/TagDefinitionTest.php:**
```php
<?php

use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\PriorityDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusEnum;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;

describe('TagDefinition - Enum Backed', function () {
    it('gets the category tag, creating if needed', function () {
        $tag = StatusDefinition::tag();
        
        expect($tag)->toBeInstanceOf(Tag::class)
            ->and($tag->slug)->toBe('status')
            ->and($tag->assignable)->toBeFalse();
    });

    it('returns values from enum', function () {
        $values = StatusDefinition::values();
        
        expect($values)->toContain('draft', 'pending', 'approved', 'rejected');
    });

    it('reports as immutable', function () {
        expect(StatusDefinition::valuesMutable())->toBeFalse();
    });

    it('creates value tags from enum', function () {
        $tag = StatusDefinition::valueTag(StatusEnum::PENDING);
        
        expect($tag->slug)->toBe('pending')
            ->and($tag->parent_id)->toBe(StatusDefinition::tag()->id);
    });

    it('validates enum values', function () {
        expect(StatusDefinition::isValidValue('pending'))->toBeTrue()
            ->and(StatusDefinition::isValidValue('invalid'))->toBeFalse();
    });
});

describe('TagDefinition - Database Backed', function () {
    it('reports as mutable', function () {
        expect(PriorityDefinition::valuesMutable())->toBeTrue();
    });

    it('returns values from database', function () {
        PriorityDefinition::addValue('High');
        PriorityDefinition::addValue('Low');
        
        $values = PriorityDefinition::values();
        
        expect($values)->toContain('high', 'low');
    });

    it('can add values', function () {
        $tag = PriorityDefinition::addValue('Critical', 'critical');
        
        expect($tag->slug)->toBe('critical')
            ->and($tag->name)->toBe('Critical');
    });

    it('can remove values', function () {
        PriorityDefinition::addValue('Temporary');
        expect(PriorityDefinition::values())->toContain('temporary');
        
        PriorityDefinition::removeValue('temporary');
        expect(PriorityDefinition::values())->not->toContain('temporary');
    });

    it('throws when adding to immutable definition', function () {
        StatusDefinition::addValue('New Status');
    })->throws(\RobinsonRyan\Taxon\Exceptions\ImmutableTagDefinitionException::class);
});

describe('TagDefinition - Model Integration', function () {
    beforeEach(function () {
        $this->model = TestModel::create(['name' => 'Test']);
    });

    it('can set tag using definition class', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING);
        
        expect($this->model->getTagValueIn('status'))->toBe('pending');
    });

    it('can set tag using enum directly', function () {
        $this->model->setTagAs(StatusDefinition::class, 'approved');
        
        expect($this->model->getTagValueIn('status'))->toBe('approved');
    });

    it('validates against definition values', function () {
        $this->model->setTagAs(StatusDefinition::class, 'invalid');
    })->throws(\RobinsonRyan\Taxon\Exceptions\InvalidTagValueException::class);

    it('gets typed value via definition', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::APPROVED);
        
        $value = $this->model->getTagAs(StatusDefinition::class);
        
        expect($value)->toBe(StatusEnum::APPROVED);
    });
});
```

**Validation:** Tests fail (RED)

---

## Step 6.2: Implement TagDefinition Base Class

**Action:** Create the TagDefinition abstract class

**src/TagDefinition.php:**
```php
<?php

namespace RobinsonRyan\Taxon;

use BackedEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionMethod;
use RobinsonRyan\Taxon\Exceptions\ImmutableTagDefinitionException;
use RobinsonRyan\Taxon\Models\Tag;

abstract class TagDefinition
{
    public static string $slug;

    public static string $name;

    public static bool $singleSelect = true;

    public static bool $global = false;

    /*
    |--------------------------------------------------------------------------
    | Value Source Configuration
    |--------------------------------------------------------------------------
    */

    public static function enum(): ?string
    {
        return null;
    }

    public static function values(): array
    {
        if ($enum = static::enum()) {
            return array_map(fn ($case) => $case->value, $enum::cases());
        }

        return static::tag()
            ->children()
            ->pluck('slug')
            ->toArray();
    }

    public static function valuesMutable(): bool
    {
        if (static::enum() !== null) {
            return false;
        }

        $reflection = new ReflectionMethod(static::class, 'values');

        return $reflection->getDeclaringClass()->getName() === self::class;
    }

    /*
    |--------------------------------------------------------------------------
    | Tag Resolution
    |--------------------------------------------------------------------------
    */

    public static function tag(): Tag
    {
        $tenantId = static::$global ? null : static::currentTenantId();

        return Tag::firstOrCreate(
            [
                'slug' => static::$slug,
                'parent_id' => null,
                'tenant_id' => $tenantId,
            ],
            [
                'name' => static::$name ?? Str::headline(static::$slug),
                'assignable' => false,
                'single_select' => static::$singleSelect,
            ]
        );
    }

    public static function valueTag(string|BackedEnum $value): Tag
    {
        $slug = $value instanceof BackedEnum ? $value->value : Str::slug($value);
        $name = $value instanceof BackedEnum ? static::enumCaseName($value) : Str::headline($value);

        return Tag::firstOrCreate(
            [
                'slug' => $slug,
                'parent_id' => static::tag()->id,
                'tenant_id' => static::$global ? null : static::currentTenantId(),
            ],
            [
                'name' => $name,
                'assignable' => true,
            ]
        );
    }

    protected static function enumCaseName(BackedEnum $case): string
    {
        return Str::headline(Str::lower($case->name));
    }

    /*
    |--------------------------------------------------------------------------
    | Value Management (Database-backed only)
    |--------------------------------------------------------------------------
    */

    public static function addValue(string $name, ?string $slug = null): Tag
    {
        if (! static::valuesMutable()) {
            throw new ImmutableTagDefinitionException(static::class);
        }

        return Tag::create([
            'name' => $name,
            'slug' => $slug ?? Str::slug($name),
            'parent_id' => static::tag()->id,
            'tenant_id' => static::$global ? null : static::currentTenantId(),
            'assignable' => true,
        ]);
    }

    public static function removeValue(string $slug): bool
    {
        if (! static::valuesMutable()) {
            throw new ImmutableTagDefinitionException(static::class);
        }

        return (bool) static::tag()
            ->children()
            ->where('slug', $slug)
            ->delete();
    }

    public static function firstOrCreateValue(string $slug, ?string $name = null): Tag
    {
        return Tag::firstOrCreate(
            [
                'slug' => $slug,
                'parent_id' => static::tag()->id,
                'tenant_id' => static::$global ? null : static::currentTenantId(),
            ],
            [
                'name' => $name ?? Str::headline($slug),
                'assignable' => true,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public static function isValidValue(string|BackedEnum $value): bool
    {
        $values = static::values();

        if (empty($values)) {
            return true;
        }

        $slug = $value instanceof BackedEnum ? $value->value : Str::slug($value);

        return in_array($slug, $values);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public static function allValueTags(): Collection
    {
        return static::tag()->children;
    }

    protected static function currentTenantId(): ?int
    {
        $config = config('taxon.tenant');

        if (! ($config['enabled'] ?? false)) {
            return null;
        }

        if ($callback = $config['callback'] ?? null) {
            return $callback();
        }

        if (($config['resolver'] ?? null) === 'auth') {
            $attribute = $config['auth_attribute'] ?? 'tenant_id';

            return auth()->user()?->{$attribute};
        }

        return null;
    }
}
```

**src/Exceptions/ImmutableTagDefinitionException.php:**
```php
<?php

namespace RobinsonRyan\Taxon\Exceptions;

use Exception;

class ImmutableTagDefinitionException extends Exception
{
    public function __construct(string $class)
    {
        parent::__construct(
            "Cannot modify values for immutable TagDefinition: {$class}"
        );
    }
}
```

**src/Exceptions/InvalidTagValueException.php:**
```php
<?php

namespace RobinsonRyan\Taxon\Exceptions;

use Exception;

class InvalidTagValueException extends Exception
{
    public function __construct(string $value, string $definition)
    {
        parent::__construct(
            "'{$value}' is not a valid value for {$definition}"
        );
    }
}
```

---

## Step 6.3: Add TagDefinition Support to HasTags

**Action:** Add methods for working with TagDefinition classes

**Add to src/HasTags.php:**

```php
    /*
    |--------------------------------------------------------------------------
    | TagDefinition Methods
    |--------------------------------------------------------------------------
    */

    public function setTagAs(string $definitionClass, string|BackedEnum $value): static
    {
        $this->validateDefinitionValue($definitionClass, $value);

        $definition = new $definitionClass();
        $valueTag = $definitionClass::valueTag($value);

        // Remove existing tags in this category
        $categoryTag = $definitionClass::tag();
        $existingIds = $categoryTag->children()->pluck('id');
        $this->tags()->detach($existingIds);

        // Attach new value
        $this->tags()->attach($valueTag->id);
        $this->load('tags');

        return $this;
    }

    public function addTagAs(string $definitionClass, string|BackedEnum $value): static
    {
        $this->validateDefinitionValue($definitionClass, $value);

        $valueTag = $definitionClass::valueTag($value);

        $this->tags()->syncWithoutDetaching([$valueTag->id]);
        $this->load('tags');

        return $this;
    }

    public function getTagAs(string $definitionClass): string|BackedEnum|null
    {
        $categoryTag = $definitionClass::tag();
        
        $valueTag = $this->tags
            ->first(fn (Tag $tag) => $tag->parent_id === $categoryTag->id);

        if (! $valueTag) {
            return null;
        }

        if ($enum = $definitionClass::enum()) {
            return $enum::tryFrom($valueTag->slug);
        }

        return $valueTag->slug;
    }

    public function hasTagAs(string $definitionClass, string|BackedEnum $value): bool
    {
        $slug = $value instanceof BackedEnum ? $value->value : Str::slug($value);
        $categoryTag = $definitionClass::tag();

        return $this->tags->contains(function (Tag $tag) use ($categoryTag, $slug) {
            return $tag->parent_id === $categoryTag->id && $tag->slug === $slug;
        });
    }

    protected function validateDefinitionValue(string $definitionClass, string|BackedEnum $value): void
    {
        if (! $definitionClass::isValidValue($value)) {
            $slug = $value instanceof BackedEnum ? $value->value : $value;

            throw new \RobinsonRyan\Taxon\Exceptions\InvalidTagValueException(
                $slug,
                $definitionClass
            );
        }
    }
```

**Add use statement at top of HasTags:**
```php
use BackedEnum;
```

**Validation:** Run `composer test tests/Feature/TagDefinitionTest.php` - all tests pass (GREEN)

---

# PHASE 7: Transition Guards

## Step 7.1: Write Transition Guard Tests

**tests/Fixtures/Definitions/StatusDefinition.php:** (update with transitions)
```php
<?php

namespace RobinsonRyan\Taxon\Tests\Fixtures\Definitions;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\Taxon\TagDefinition;

enum StatusEnum: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
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

    public static function default(): StatusEnum
    {
        return StatusEnum::DRAFT;
    }

    public static function transitions(): array
    {
        return [
            StatusEnum::DRAFT->value => [
                StatusEnum::PENDING,
            ],
            StatusEnum::PENDING->value => [
                StatusEnum::DRAFT,
                StatusEnum::APPROVED,
                StatusEnum::REJECTED,
            ],
            StatusEnum::APPROVED->value => [
                // Terminal
            ],
            StatusEnum::REJECTED->value => [
                StatusEnum::DRAFT,
            ],
        ];
    }

    public function canTransition(Model $model, ?StatusEnum $from, StatusEnum $to, $user = null): bool
    {
        if ($from === null) {
            return $to === static::default();
        }

        $allowed = static::transitions()[$from->value] ?? [];

        if (! in_array($to, $allowed)) {
            return false;
        }

        // Example: only admins can approve
        if ($to === StatusEnum::APPROVED && $user && ! $user->isAdmin()) {
            return false;
        }

        return true;
    }

    public function availableTransitions(Model $model, $user = null): array
    {
        $current = $model->getTagAs(static::class);

        if ($current === null) {
            return [static::default()];
        }

        $possible = static::transitions()[$current->value] ?? [];

        return array_filter(
            $possible,
            fn (StatusEnum $status) => $this->canTransition($model, $current, $status, $user)
        );
    }
}
```

**tests/Feature/TransitionGuardsTest.php:**
```php
<?php

use RobinsonRyan\Taxon\Exceptions\InvalidTransitionException;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusDefinition;
use RobinsonRyan\Taxon\Tests\Fixtures\Definitions\StatusEnum;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestUser;

describe('Transition Guards', function () {
    beforeEach(function () {
        $this->model = TestModel::create(['name' => 'Test']);
        $this->user = TestUser::create(['name' => 'User', 'email' => 'user@test.com']);
        $this->admin = TestUser::create(['name' => 'Admin', 'email' => 'admin@test.com', 'is_admin' => true]);
    });

    it('allows valid transitions', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::DRAFT);
        
        $this->model->transitionTo(StatusDefinition::class, StatusEnum::PENDING, $this->user);
        
        expect($this->model->getTagAs(StatusDefinition::class))->toBe(StatusEnum::PENDING);
    });

    it('blocks invalid transitions', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::DRAFT);
        
        $this->model->transitionTo(StatusDefinition::class, StatusEnum::APPROVED, $this->user);
    })->throws(InvalidTransitionException::class);

    it('respects permission guards', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING);
        
        // Regular user cannot approve
        $this->model->transitionTo(StatusDefinition::class, StatusEnum::APPROVED, $this->user);
    })->throws(InvalidTransitionException::class);

    it('allows admins through permission guards', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING);
        
        $this->model->transitionTo(StatusDefinition::class, StatusEnum::APPROVED, $this->admin);
        
        expect($this->model->getTagAs(StatusDefinition::class))->toBe(StatusEnum::APPROVED);
    });

    it('returns available transitions for current state', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING);
        $definition = new StatusDefinition();
        
        $available = $definition->availableTransitions($this->model, $this->user);
        
        expect($available)->toContain(StatusEnum::DRAFT, StatusEnum::REJECTED)
            ->and($available)->not->toContain(StatusEnum::APPROVED);
    });

    it('returns admin-only transitions for admins', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::PENDING);
        $definition = new StatusDefinition();
        
        $available = $definition->availableTransitions($this->model, $this->admin);
        
        expect($available)->toContain(StatusEnum::APPROVED);
    });

    it('blocks transitions from terminal states', function () {
        $this->model->setTagAs(StatusDefinition::class, StatusEnum::APPROVED);
        
        $this->model->transitionTo(StatusDefinition::class, StatusEnum::PENDING, $this->admin);
    })->throws(InvalidTransitionException::class);
});
```

**Validation:** Tests fail (RED)

---

## Step 7.2: Implement Transition Guards

**src/Exceptions/InvalidTransitionException.php:**
```php
<?php

namespace RobinsonRyan\Taxon\Exceptions;

use BackedEnum;
use Exception;
use Illuminate\Database\Eloquent\Model;

class InvalidTransitionException extends Exception
{
    public function __construct(
        public readonly Model $model,
        public readonly ?BackedEnum $from,
        public readonly BackedEnum $to,
    ) {
        $fromLabel = $from?->value ?? 'none';

        parent::__construct(
            "Cannot transition from '{$fromLabel}' to '{$to->value}'."
        );
    }
}
```

**Add to src/HasTags.php:**

```php
    /*
    |--------------------------------------------------------------------------
    | Transition Methods
    |--------------------------------------------------------------------------
    */

    public function transitionTo(string $definitionClass, BackedEnum $to, $user = null): static
    {
        $definition = new $definitionClass();
        $from = $this->getTagAs($definitionClass);

        if (! method_exists($definition, 'canTransition')) {
            // No guards defined, just set it
            return $this->setTagAs($definitionClass, $to);
        }

        if (! $definition->canTransition($this, $from, $to, $user)) {
            throw new \RobinsonRyan\Taxon\Exceptions\InvalidTransitionException(
                $this,
                $from,
                $to
            );
        }

        return $this->setTagAs($definitionClass, $to);
    }
```

**Validation:** Run `composer test tests/Feature/TransitionGuardsTest.php` - all tests pass (GREEN)

---

# PHASE 8: Tenant Scoping

## Step 8.1: Write Tenant Scoping Tests

**tests/Feature/TenantScopingTest.php:**
```php
<?php

use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;

describe('Tenant Scoping', function () {
    beforeEach(function () {
        config()->set('taxon.tenant.enabled', true);
        config()->set('taxon.tenant.column', 'tenant_id');
    });

    it('creates tags with tenant_id', function () {
        $tag = Tag::create([
            'name' => 'Tenant Tag',
            'tenant_id' => 1,
        ]);
        
        expect($tag->tenant_id)->toBe(1);
    });

    it('separates tags by tenant', function () {
        Tag::create(['name' => 'Shared Name', 'tenant_id' => 1]);
        Tag::create(['name' => 'Shared Name', 'tenant_id' => 2]);
        
        expect(Tag::where('name', 'Shared Name')->count())->toBe(2);
    });

    it('enforces uniqueness within tenant', function () {
        Tag::create(['name' => 'Unique', 'slug' => 'unique', 'tenant_id' => 1]);
        
        Tag::create(['name' => 'Unique', 'slug' => 'unique', 'tenant_id' => 1]);
    })->throws(\Illuminate\Database\QueryException::class);

    it('allows same slug in different tenants', function () {
        $tag1 = Tag::create(['name' => 'Status', 'tenant_id' => 1]);
        $tag2 = Tag::create(['name' => 'Status', 'tenant_id' => 2]);
        
        expect($tag1->id)->not->toBe($tag2->id);
    });

    it('scopes category children by tenant', function () {
        $cat1 = Tag::createCategory('Status', tenantId: 1);
        $cat1->addChild('Active');
        
        $cat2 = Tag::createCategory('Status', tenantId: 2);
        $cat2->addChild('Inactive');
        
        expect($cat1->children)->toHaveCount(1)
            ->and($cat1->children->first()->slug)->toBe('active');
        
        expect($cat2->children)->toHaveCount(1)
            ->and($cat2->children->first()->slug)->toBe('inactive');
    });
});

describe('Global Tags', function () {
    beforeEach(function () {
        config()->set('taxon.tenant.enabled', true);
    });

    it('creates global tags with null tenant_id', function () {
        $tag = Tag::create([
            'name' => 'Global Tag',
            'tenant_id' => null,
        ]);
        
        expect($tag->tenant_id)->toBeNull();
    });

    it('global tags are unique by slug alone', function () {
        Tag::create(['name' => 'System', 'slug' => 'system', 'tenant_id' => null]);
        
        Tag::create(['name' => 'System', 'slug' => 'system', 'tenant_id' => null]);
    })->throws(\Illuminate\Database\QueryException::class);
});
```

**Validation:** Tests fail (RED)

---

## Step 8.2: Implement Tenant Scoping

The tenant scoping is primarily enforced by the database unique constraint. The Tag model and HasTags trait need to respect tenant_id when resolving tags.

**Update resolution methods in src/HasTags.php to pass tenant:**

```php
    protected function resolveOrCreateTags(array $tags): Collection
    {
        $tenantId = $this->getTagTenantId();

        return collect($tags)->map(function ($tag) use ($tenantId) {
            $slug = Str::slug($tag);

            return Tag::firstOrCreate(
                ['slug' => $slug, 'parent_id' => null, 'tenant_id' => $tenantId],
                ['name' => $tag]
            );
        });
    }

    protected function resolveTags(array $tags): Collection
    {
        $slugs = collect($tags)->map(fn ($t) => Str::slug($t));
        $tenantId = $this->getTagTenantId();

        return Tag::whereIn('slug', $slugs)
            ->whereNull('parent_id')
            ->where('tenant_id', $tenantId)
            ->get();
    }

    protected function resolveCategoryTag(string $category): Tag
    {
        $slug = Str::slug($category);
        $tenantId = $this->getTagTenantId();

        $tag = Tag::where('slug', $slug)
            ->whereNull('parent_id')
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $tag && config('taxon.auto_create', true)) {
            $tag = Tag::createCategory($category, tenantId: $tenantId);
        }

        if (! $tag) {
            throw new \RobinsonRyan\Taxon\Exceptions\TagNotFoundException(
                "Category tag '{$category}' not found."
            );
        }

        return $tag;
    }

    protected function resolveOrCreateValueTag(Tag $category, string $value): Tag
    {
        $slug = Str::slug($value);

        return Tag::firstOrCreate(
            [
                'slug' => $slug,
                'parent_id' => $category->id,
                'tenant_id' => $category->tenant_id,
            ],
            ['name' => $value, 'assignable' => true]
        );
    }

    protected function getTagTenantId(): ?int
    {
        $config = config('taxon.tenant');

        if (! ($config['enabled'] ?? false)) {
            return null;
        }

        // If model has tenant column, use it
        $column = $config['column'] ?? 'tenant_id';
        if (isset($this->{$column})) {
            return $this->{$column};
        }

        // Fall back to resolver
        if ($callback = $config['callback'] ?? null) {
            return $callback();
        }

        if (($config['resolver'] ?? null) === 'auth') {
            $attribute = $config['auth_attribute'] ?? 'tenant_id';

            return auth()->user()?->{$attribute};
        }

        return null;
    }
```

**Validation:** Run `composer test tests/Feature/TenantScopingTest.php` - all tests pass (GREEN)

---

## Step 8.3: Write UUID7 Tests

**Action:** Write tests to verify UUID7 configuration works correctly

**tests/Feature/Uuid7Test.php:**
```php
<?php

use Illuminate\Support\Str;
use RobinsonRyan\Taxon\Models\Tag;
use RobinsonRyan\Taxon\Tests\Fixtures\Models\TestModel;

describe('UUID7 Support', function () {
    beforeEach(function () {
        $this->useUuid7();
    });

    it('creates tags with UUID7 primary keys', function () {
        $tag = Tag::create(['name' => 'Test Tag']);
        
        expect($tag->id)->toBeString()
            ->and(Str::isUuid($tag->id))->toBeTrue();
    });

    it('creates parent-child relationships with UUID7', function () {
        $parent = Tag::createCategory('Status');
        $child = $parent->addChild('Pending');
        
        expect($parent->id)->toBeString()
            ->and($child->parent_id)->toBe($parent->id)
            ->and($child->parent->id)->toBe($parent->id);
    });

    it('creates taggable pivot records with UUID7', function () {
        $model = TestModel::create(['name' => 'Test']);
        $model->tag('important');
        
        expect($model->id)->toBeString()
            ->and($model->tags->first()->id)->toBeString();
    });

    it('queries work with UUID7 keys', function () {
        $tag = Tag::create(['name' => 'Findable']);
        
        $found = Tag::find($tag->id);
        
        expect($found)->not->toBeNull()
            ->and($found->id)->toBe($tag->id);
    });

    it('category tagging works with UUID7', function () {
        $status = Tag::createCategory('Status');
        $status->addChildren(['pending', 'complete']);
        
        $model = TestModel::create(['name' => 'Test']);
        $model->setTag('status', 'pending');
        
        expect($model->getTagValueIn('status'))->toBe('pending');
    });
});

describe('Incrementing ID (Default)', function () {
    it('creates tags with incrementing IDs by default', function () {
        $tag = Tag::create(['name' => 'Test Tag']);
        
        expect($tag->id)->toBeInt();
    });
});
```

**Validation:** Run `composer test tests/Feature/Uuid7Test.php` - all tests pass (GREEN)

---

# PHASE 9: Tags Tagging Tags (RBAC Pattern)

## Step 9.1: Write Tags Tagging Tags Tests

**tests/Feature/TagsTaggingTagsTest.php:**
```php
<?php

use RobinsonRyan\Taxon\Models\Tag;

describe('Tags Tagging Tags', function () {
    beforeEach(function () {
        // Create Roles category
        $this->roles = Tag::createCategory('Roles');
        $this->adminRole = $this->roles->addChild('admin');
        $this->editorRole = $this->roles->addChild('editor');
        
        // Create Permissions category
        $this->permissions = Tag::createCategory('Permissions');
        $this->createPost = $this->permissions->addChild('posts.create');
        $this->readPost = $this->permissions->addChild('posts.read');
        $this->deletePost = $this->permissions->addChild('posts.delete');
    });

    it('can tag a tag with another tag', function () {
        // Admin role gets all permissions
        $this->adminRole->tag('posts.create');
        
        expect($this->adminRole->tags)->toHaveCount(1)
            ->and($this->adminRole->hasTag('posts.create'))->toBeTrue();
    });

    it('role can have multiple permission tags', function () {
        $this->adminRole->tag(['posts.create', 'posts.read', 'posts.delete']);
        
        expect($this->adminRole->tags)->toHaveCount(3);
    });

    it('can query permissions for a role', function () {
        $this->adminRole->tag(['posts.create', 'posts.read', 'posts.delete']);
        $this->editorRole->tag(['posts.create', 'posts.read']);
        
        $adminPerms = $this->adminRole->tags->pluck('slug')->toArray();
        $editorPerms = $this->editorRole->tags->pluck('slug')->toArray();
        
        expect($adminPerms)->toContain('posts.delete')
            ->and($editorPerms)->not->toContain('posts.delete');
    });

    it('can sync permissions on a role', function () {
        $this->adminRole->tag(['posts.create', 'posts.read']);
        $this->adminRole->retag(['posts.delete']);
        
        expect($this->adminRole->tags)->toHaveCount(1)
            ->and($this->adminRole->hasTag('posts.delete'))->toBeTrue();
    });

    it('Tag model uses HasTags trait', function () {
        expect(class_uses(Tag::class))->toContain(\RobinsonRyan\Taxon\HasTags::class);
    });
});
```

**Validation:** Tests pass (Tag model already has HasTags trait)

---

# PHASE 10: Documentation

## Step 10.1: Generate API Documentation

**Action:** Create comprehensive documentation files

**docs/README.md:**
```markdown
# Laravel Taggable

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
```

**docs/installation.md:**
```markdown
# Installation

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x

## Install via Composer

```bash
composer require robinsonryan/taxon
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=taxon-config
```

## Configure ID Type (Optional)

Before running migrations, set your preferred ID type in `config/taxon.php`:

```php
// Default: auto-incrementing integers
'id_type' => 'incrementing',

// Or: UUID7 for distributed systems
'id_type' => 'uuid7',
```

**Note:** This must be set before running migrations. Changing after data exists requires a manual migration.

## Run Migrations

```bash
php artisan vendor:publish --tag=taxon-migrations
php artisan migrate
```

## Configuration

Edit `config/taxon.php` to customize:

```php
return [
    // Table names
    'tables' => [
        'tags' => 'tags',
        'taggables' => 'taggables',
    ],

    // Primary key type: 'incrementing' or 'uuid7'
    'id_type' => 'incrementing',

    // Auto-create tags on first use
    'auto_create' => true,

    // Multi-tenant configuration
    'tenant' => [
        'enabled' => false,
        'column' => 'tenant_id',
        'resolver' => 'auth',
        'auth_attribute' => 'account_id',
    ],
];
```

## Local Development with DDEV

For package development:

```bash
cd ~/dev/php/packages/robinsonryan/taxon
ddev start
ddev composer install
ddev test
```

Tests use SQLite in-memory and don't require the DDEV database.
```

**docs/basic-usage.md:**
```markdown
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
```

**docs/categories.md:**
```markdown
# Category Tagging

Categories let you organize tags into groups with single or multi-select behavior.

## Creating Categories

```php
use RobinsonRyan\Taxon\Models\Tag;

// Single-select category
$status = Tag::createCategory('Status', singleSelect: true);
$status->addChildren(['draft', 'published', 'archived']);

// Multi-select category
$topics = Tag::createCategory('Topics', singleSelect: false);
$topics->addChildren(['php', 'laravel', 'vue', 'react']);
```

## Using Categories

### Single-Select (setTag)

```php
$post->setTag('status', 'draft');
$post->setTag('status', 'published'); // Replaces 'draft'

$post->getTagIn('status');       // Tag model
$post->getTagValueIn('status');  // 'published'
```

### Multi-Select (addTag)

```php
$post->addTag('topics', 'php');
$post->addTag('topics', 'laravel');
$post->addTags('topics', ['vue', 'react']);

$post->tagsIn('topics');  // Collection of Tags
```

### Removing

```php
$post->removeTag('topics', 'react');  // Remove specific
$post->removeTagsIn('topics');         // Remove all in category
```

## Checking Categories

```php
$post->hasTagIn('status', 'published');
$post->hasAnyTagIn('topics', ['php', 'python']);
$post->hasAllTagsIn('topics', ['php', 'laravel']);
```

## Query Scopes

```php
Post::withTagIn('status', 'published')->get();
Post::withAnyTagIn('topics', ['php', 'vue'])->get();
Post::withoutTagIn('status', 'archived')->get();
```
```

**docs/tag-definitions.md:**
```markdown
# Tag Definitions

Tag Definitions are optional classes that provide validation, type-safety, and transition guards for categories.

## Value Sources

### Enum-Backed (Immutable)

```php
enum PostStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';
}

class PostStatusDefinition extends TagDefinition
{
    public static string $slug = 'post-status';
    public static bool $singleSelect = true;
    
    public static function enum(): string
    {
        return PostStatus::class;
    }
}
```

### Database-Backed (Mutable)

```php
class Priority extends TagDefinition
{
    public static string $slug = 'priority';
    // No enum() override = database-backed
}

// Add values via admin UI
Priority::addValue('Critical');
Priority::addValue('High');
Priority::removeValue('low');
```

## Using Definitions

```php
$post->setTagAs(PostStatusDefinition::class, PostStatus::DRAFT);
$post->getTagAs(PostStatusDefinition::class); // Returns PostStatus enum

// Invalid values throw InvalidTagValueException
$post->setTagAs(PostStatusDefinition::class, 'invalid');
```

## Transition Guards

```php
class PostStatusDefinition extends TagDefinition
{
    public static function transitions(): array
    {
        return [
            'draft' => [PostStatus::PUBLISHED],
            'published' => [PostStatus::ARCHIVED],
            'archived' => [], // Terminal state
        ];
    }
    
    public function canTransition(Model $model, $from, $to, $user): bool
    {
        $allowed = static::transitions()[$from->value] ?? [];
        return in_array($to, $allowed);
    }
}

// Use with transition validation
$post->transitionTo(PostStatusDefinition::class, PostStatus::PUBLISHED, $user);
```
```

**docs/magic-attributes.md:**
```markdown
# Magic Attribute Access

Access and set tag values as native model properties.

## Configuration

Declare which tag categories should be accessible as properties:

```php
use RobinsonRyan\Taxon\HasTags;

class Report extends Model
{
    use HasTags;
    
    protected array $tagAttributes = [
        'status',                              // string-based category
        'priority' => PriorityDefinition::class, // TagDefinition-backed
    ];
}
```

## String-Based Categories

For simple categories, tags are accessed as string values:

```php
// Set via property
$report->status = 'pending';

// Get via property
$report->status;  // 'pending'

// Equivalent to
$report->setTag('status', 'pending');
$report->getTagValueIn('status');
```

## TagDefinition-Backed

For categories backed by a TagDefinition class, you get typed enum values:

```php
// Set via property (string or enum)
$report->priority = Priority::HIGH;
$report->priority = 'high';

// Get via property (returns enum)
$report->priority;  // Priority::HIGH

// Equivalent to
$report->setTagAs(PriorityDefinition::class, Priority::HIGH);
$report->getTagAs(PriorityDefinition::class);
```

## How It Works

The `HasTags` trait overrides Laravel's `getAttribute()` and `setAttribute()` methods. When accessing a declared tag attribute:

1. The trait checks if the key exists in `$tagAttributes`
2. If it's a string entry, it uses `getTagValueIn()` / `setTag()`
3. If it maps to a TagDefinition class, it uses `getTagAs()` / `setTagAs()`
4. Otherwise, it delegates to Laravel's normal attribute handling

## Important Notes

- Tag attributes take precedence over database columns with the same name
- Don't declare a column in both `$tagAttributes` and as a database column
- The trait calls parent methods for non-tag attributes, preserving normal behavior
```

**docs/tenant-scoping.md:**
```markdown
# Tenant Scoping

Taxon supports multi-tenant applications where each tenant has isolated tags.

## Configuration

```php
// config/taxon.php
'tenant' => [
    'enabled' => true,
    'column' => 'tenant_id',  // or 'account_id'
    'resolver' => 'auth',
    'auth_attribute' => 'account_id',
],
```

## Resolution Methods

### From Model

If your model has a tenant column, tags inherit it:

```php
$post = Post::create(['title' => 'Test', 'account_id' => 1]);
$post->tag('featured'); // Tag created with tenant_id = 1
```

### From Auth

```php
'resolver' => 'auth',
'auth_attribute' => 'account_id',
```

### Custom Callback

```php
'resolver' => 'callback',
'callback' => fn() => app('tenant')->id,
```

## Global Tags

Tags with `tenant_id = null` are global and shared across tenants.

```php
// In TagDefinition
public static bool $global = true;

// Or manually
Tag::create(['name' => 'System Status', 'tenant_id' => null]);
```
```

**docs/api-reference.md:**
```markdown
# API Reference

## HasTags Trait

### Direct Tagging

| Method | Description |
|--------|-------------|
| `tag(string\|array $tags)` | Attach tags |
| `untag(string\|array $tags)` | Detach tags |
| `retag(array $tags)` | Sync tags (replace all) |
| `detachAllTags()` | Remove all tags |

### Direct Tag Checks

| Method | Description |
|--------|-------------|
| `hasTag(string $tag): bool` | Check for single tag |
| `hasAnyTag(array $tags): bool` | Check for any matching |
| `hasAllTags(array $tags): bool` | Check for all matching |

### Category Tagging

| Method | Description |
|--------|-------------|
| `setTag(string $category, string $value)` | Single-select assign |
| `addTag(string $category, string $value)` | Multi-select add |
| `addTags(string $category, array $values)` | Multi-select bulk add |
| `removeTag(string $category, string $value)` | Remove specific |
| `removeTagsIn(string $category)` | Remove all in category |

### Category Tag Access

| Method | Description |
|--------|-------------|
| `tagsIn(string $category): Collection` | Get all tags in category |
| `getTagIn(string $category): ?Tag` | Get first tag in category |
| `getTagValueIn(string $category): ?string` | Get slug of first tag |

### Magic Attribute Access

Configure in model:
```php
protected array $tagAttributes = [
    'status',                           // string category
    'priority' => PriorityDef::class,   // TagDefinition
];
```

| Access | Equivalent Method |
|--------|-------------------|
| `$model->status` (get) | `$model->getTagValueIn('status')` |
| `$model->status = 'x'` (set) | `$model->setTag('status', 'x')` |
| `$model->priority` (get, with Def) | `$model->getTagAs(PriorityDef::class)` |
| `$model->priority = X` (set, with Def) | `$model->setTagAs(PriorityDef::class, X)` |

### TagDefinition Methods

| Method | Description |
|--------|-------------|
| `setTagAs(string $class, mixed $value)` | Set with validation |
| `addTagAs(string $class, mixed $value)` | Add with validation |
| `getTagAs(string $class): mixed` | Get typed value |
| `hasTagAs(string $class, mixed $value): bool` | Check with type |
| `transitionTo(string $class, BackedEnum $to, $user)` | Guarded transition |

### Query Scopes

| Scope | Description |
|-------|-------------|
| `withTag(string $tag)` | Has direct tag |
| `withAnyTag(array $tags)` | Has any of tags |
| `withAllTags(array $tags)` | Has all tags |
| `withoutTag(string $tag)` | Missing tag |
| `withTagIn(string $category, string $value)` | Has tag in category |
| `withAnyTagIn(string $category, array $values)` | Has any in category |
| `withoutTagIn(string $category, string $value)` | Missing from category |

## Tag Model

### Factory Methods

| Method | Description |
|--------|-------------|
| `Tag::createCategory(string $name, ...)` | Create category tag |
| `Tag::createValue(string $name, int $parentId, ...)` | Create value tag |

### Instance Methods

| Method | Description |
|--------|-------------|
| `addChild(string $name): Tag` | Add child tag |
| `addChildren(array $names): Collection` | Add multiple children |
| `syncChildren(array $values): Collection` | Sync children |
| `destroy(): bool` | Delete if not in use |
| `forceDestroy(): bool` | Delete regardless |

### Query Scopes

| Scope | Description |
|-------|-------------|
| `roots()` | Where parent_id is null |
| `categories()` | Where has children |
| `assignable()` | Where assignable = true |
| `slug(string $slug)` | Where slug matches |
| `childrenOf(string\|int $parent)` | Children of parent |

## TagDefinition Class

### Static Properties

| Property | Type | Description |
|----------|------|-------------|
| `$slug` | string | Category slug |
| `$name` | string | Display name |
| `$singleSelect` | bool | Default: true |
| `$global` | bool | Ignore tenant. Default: false |

### Static Methods

| Method | Description |
|--------|-------------|
| `enum(): ?string` | Return enum class |
| `values(): array` | Get valid values |
| `valuesMutable(): bool` | Can add/remove values? |
| `tag(): Tag` | Get category tag |
| `valueTag(mixed $value): Tag` | Get value tag |
| `addValue(string $name): Tag` | Add value (mutable only) |
| `removeValue(string $slug): bool` | Remove value (mutable only) |
| `isValidValue(mixed $value): bool` | Validate value |
```

---

## Step 10.2: Update README

**README.md:** (in package root)
```markdown
# Taxon

[![Tests](https://github.com/robinsonryan/taxon/actions/workflows/tests.yml/badge.svg)](https://github.com/robinsonryan/taxon/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/github/v/release/robinsonryan/taxon)](https://github.com/robinsonryan/taxon/releases)

A flexible hierarchical tagging system for Laravel with tenant scoping and class-based tag definitions.

## Features

- 🏷️ **Direct Tagging** - Simple tag/untag operations
- 📂 **Categories** - Organize tags with single/multi-select
- ✨ **Magic Attributes** - Access tags as model properties
- 🔒 **Tag Definitions** - Enum validation & transition guards
- 🏢 **Multi-tenant** - Isolated tags per tenant
- 🔗 **Tags Tag Tags** - Build RBAC or taxonomies
- 🆔 **Flexible IDs** - Incrementing or UUID7 primary keys

## Installation

```bash
composer require robinsonryan/taxon
php artisan vendor:publish --tag=taxon-migrations
php artisan migrate
```

## Quick Start

```php
use RobinsonRyan\Taxon\HasTags;

class Post extends Model
{
    use HasTags;
    
    // Enable magic attribute access
    protected array $tagAttributes = ['status', 'priority'];
}

// Tag it
$post->tag('featured');
$post->setTag('status', 'published');

// Magic attribute access
$post->status = 'draft';
$post->status; // 'draft'

// Query it
Post::withTag('featured')->get();
Post::withTagIn('status', 'published')->get();
```

## Documentation

See the [docs](docs/) folder for complete documentation.

## Testing

```bash
composer test
```

## License

MIT
```

---

## Step 10.3: Create GitHub Actions Workflow

**.github/workflows/tests.yml:**
```yaml
name: Tests

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    
    strategy:
      fail-fast: true
      matrix:
        php: [8.2, 8.3, 8.4]
        laravel: [11.*, 12.*]
        include:
          - laravel: 11.*
            testbench: 9.*
          - laravel: 12.*
            testbench: 10.*

    name: P${{ matrix.php }} - L${{ matrix.laravel }}

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: mbstring, pdo, pdo_sqlite
          coverage: none

      - name: Install dependencies
        run: |
          composer require "laravel/framework:${{ matrix.laravel }}" "orchestra/testbench:${{ matrix.testbench }}" --no-interaction --no-update
          composer update --prefer-dist --no-interaction

      - name: Run Pint
        run: composer lint:check

      - name: Run PHPStan
        run: composer analyze

      - name: Run Tests
        run: composer test
```

---

# Final Checklist

Before considering the package complete:

- [ ] DDEV environment running (`ddev start`)
- [ ] All tests pass (`ddev test` or `composer test`)
- [ ] No linting errors (`composer lint:check`)
- [ ] No static analysis errors (`composer analyze`)
- [ ] UUID7 tests pass when configured
- [ ] Documentation is complete and accurate
- [ ] CHANGELOG.md has initial entry
- [ ] Git repository initialized with .gitignore
- [ ] GitHub Actions workflow runs successfully

---

# Build Execution Instructions for Claude Code

1. **Create package directory**: Start fresh in an empty directory
2. **Execute phases sequentially**: Complete each phase before moving to the next
3. **TDD discipline**: Write tests first (RED), implement (GREEN), refactor
4. **Validate each step**: Run the specified validation command before proceeding
5. **Commit atomically**: After each phase passes validation, commit with descriptive message
6. **Run full suite periodically**: `composer quality` runs lint, analyze, and test together

When implementing, prefer:
- Explicit over implicit
- Small, focused methods
- Clear naming over comments
- Type hints everywhere
- Return types on all methods

This spec should produce a production-ready package suitable for immediate use in your applications.
