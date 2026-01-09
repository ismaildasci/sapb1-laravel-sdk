# Multi-Tenant Support

The SDK provides multi-tenant session isolation for SaaS applications connecting to multiple SAP B1 databases.

## Basic Usage

```php
use SapB1\MultiTenant\TenantManager;

$tenantManager = app(TenantManager::class);

// Set current tenant
$tenantManager->setTenant('tenant-123');

// Execute in tenant context
$tenantManager->forTenant('tenant-456', function() {
    return SapB1::get('Orders')->value();
});
```

## Tenant Resolver

Implement custom tenant resolution:

```php
use SapB1\Contracts\TenantResolverInterface;

class MyTenantResolver implements TenantResolverInterface
{
    public function resolve(): ?string
    {
        return auth()->user()?->tenant_id;
    }

    public function getConfig(string $tenantId): ?array
    {
        return Tenant::find($tenantId)?->sap_config;
    }
}

// Register resolver
$tenantManager->setResolver(new MyTenantResolver());
```

## Database Resolver

Built-in resolver for database-stored tenant configs:

```php
use SapB1\MultiTenant\DatabaseTenantResolver;

$resolver = new DatabaseTenantResolver(
    table: 'tenants',
    tenantColumn: 'id',
    configColumn: 'sap_config'
);

$tenantManager->setResolver($resolver);
```

## Configuration Per Tenant

Store tenant SAP configs in your tenants table:

```php
// Migration
Schema::create('tenants', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->json('sap_config'); // Store SAP connection details
    $table->timestamps();
});

// Tenant sap_config structure
[
    'base_url' => 'https://tenant-sap-server:50000',
    'company_db' => 'TENANT_COMPANY',
    'username' => 'manager',
    'password' => 'encrypted_password',
]
```

## Middleware Integration

Use TenantMiddleware for automatic header injection:

```php
use SapB1\Middleware\TenantMiddleware;

SapB1Client::pushMiddleware(new TenantMiddleware($tenantManager));
```

## Best Practices

1. **Session Isolation**: Each tenant gets separate sessions
2. **Config Encryption**: Encrypt sensitive config values
3. **Connection Pooling**: Consider per-tenant connection pools
4. **Logging**: Include tenant ID in all logs for debugging
