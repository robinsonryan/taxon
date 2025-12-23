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
