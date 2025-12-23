# Tenant Scoping

Taxon supports multi-tenant applications with automatic tag isolation.

## Configuration

```php
// config/taxon.php
'tenant' => [
    'enabled' => true,
    'column' => 'tenant_id',
    'resolver' => 'auth',
    'auth_attribute' => 'account_id',
],
```

## How It Works

When tenant scoping is enabled:

1. Tags are created with the current tenant's ID
2. Tag lookups are scoped to the current tenant
3. The unique constraint prevents slug collisions within a tenant

## Tenant Resolution

### From Auth User

```php
'tenant' => [
    'resolver' => 'auth',
    'auth_attribute' => 'account_id',
],
```

Reads `auth()->user()->account_id` to get the tenant ID.

### From Callback

```php
'tenant' => [
    'callback' => fn() => app('tenant')->id,
],
```

### From Model

If the model being tagged has the tenant column, it's used automatically:

```php
// Model has `tenant_id` column
$post->tenant_id = 5;
$post->tag('featured'); // Tag created with tenant_id = 5
```

## Global Tags

Set `tenant_id` to `null` for tags shared across all tenants:

```php
Tag::createCategory('System', tenantId: null);
```

Or use the `$global` property in TagDefinitions:

```php
class StatusDefinition extends TagDefinition
{
    public static bool $global = true; // No tenant scoping
}
```

## Database Schema

The unique constraint ensures slug uniqueness per tenant:

```sql
UNIQUE (slug, parent_id, tenant_id)
```

This allows the same slug in different tenants while preventing duplicates within a tenant.
