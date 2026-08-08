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

### If your own models are keyed differently from your tags

`id_type` governs Taxon's own primary keys. The `taggables.taggable_id` column holds
**your** models' primary keys, and those need not match — an app can run UUID7 tags
over auto-incrementing posts, or the reverse. `taggable_id_type` follows `id_type`
unless you say otherwise:

```php
// Tags get UUID7 keys, but the models you tag still use integer ids
'id_type' => 'uuid7',
'taggable_id_type' => 'incrementing',
```

Leave it `null` (the default) when everything uses one key type.

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

    // Taxon's own primary key type: 'incrementing' or 'uuid7'
    'id_type' => 'incrementing',

    // Your models' key type, as stored in taggables.taggable_id.
    // null follows id_type; override only for a mixed-key app.
    'taggable_id_type' => null,

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
