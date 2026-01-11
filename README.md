# SAP Business One Laravel SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ismaildasci/laravel-sapb1.svg?style=flat-square)](https://packagist.org/packages/ismaildasci/laravel-sapb1)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/ismaildasci/laravel-sapb1/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ismaildasci/laravel-sapb1/actions?query=workflow%3Arun-tests+branch%3Amain)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/ismaildasci/laravel-sapb1/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/ismaildasci/laravel-sapb1/actions?query=workflow%3Aphpstan+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ismaildasci/laravel-sapb1.svg?style=flat-square)](https://packagist.org/packages/ismaildasci/laravel-sapb1)

A Laravel SDK for SAP Business One Service Layer. Handles sessions, builds OData queries, manages connections, and gets out of your way.

```php
use SapB1\Facades\SapB1;

// It just works
$partners = SapB1::get('BusinessPartners')->value();

// Query builder for complex stuff
$orders = SapB1::query()
    ->select('DocEntry', 'DocNum', 'CardCode', 'DocTotal')
    ->where('DocDate', 'ge', '2024-01-01')
    ->whereContains('CardName', 'Corp')
    ->orderByDesc('DocDate')
    ->expand('DocumentLines')
    ->get('Orders')
    ->value();

// CRUD operations
SapB1::create('BusinessPartners', ['CardCode' => 'C001', 'CardName' => 'Acme Corp']);
SapB1::update('BusinessPartners', 'C001', ['Phone1' => '555-1234']);
SapB1::delete('BusinessPartners', 'C001');
```

## Installation

```bash
composer require ismaildasci/laravel-sapb1
php artisan vendor:publish --tag="sap-b1-config"
```

Add to `.env`:

```env
SAP_B1_URL=https://your-sap-server:50000
SAP_B1_COMPANY_DB=YOUR_COMPANY_DB
SAP_B1_USERNAME=manager
SAP_B1_PASSWORD=your_password
```

## Documentation

Full documentation is available in the [docs](docs/README.md) folder:

**Getting Started**
- [Quick Start](docs/quick-start.md) - Get up and running in minutes
- [Installation](docs/installation.md) & [Configuration](docs/configuration.md)

**Core Concepts**
- [Making Requests](docs/requests.md) - CRUD operations & request methods
- [OData Query Builder](docs/odata-query-builder.md) - Fluent query building
- [Working with Responses](docs/responses.md) - Response handling & pagination
- [Multiple Connections](docs/multiple-connections.md) - Multi-server setup

**Features**
- [Batch Operations](docs/batch-operations.md) - Multiple requests in one call
- [Session Pool](docs/session-pool.md) - High-concurrency scenarios
- [Circuit Breaker](docs/circuit-breaker.md) - Resilience & fault tolerance
- [Query Caching](docs/caching.md) - Performance optimization
- [Attachments](docs/attachments.md) - File uploads & downloads
- [SQL Queries](docs/sql-queries.md) - Stored queries & semantic layer
- [Multi-Tenant](docs/multi-tenant.md) - Multi-tenant session isolation

**Operations & Debugging**
- [Health Checks](docs/health-checks.md) - Connection monitoring
- [Artisan Commands](docs/artisan-commands.md) - CLI tools
- [Events](docs/events.md) - Event lifecycle & listeners
- [Error Handling](docs/error-handling.md) - Exceptions & error codes

**Testing**
- [Testing](docs/testing.md) - Mocking & factories
- [Testing Factories](docs/testing-factories.md) - Entity factory patterns

## Features

**Core** - Fluent OData query builder, automatic session management, multiple connections, rich response handling, request middleware pipeline.

**Performance** - Batch operations, query caching, request compression, connection pooling, schema caching.

**Resilience** - Circuit breaker pattern, automatic retries with exponential backoff, session auto-refresh, rate limit handling, human-readable error messages.

**Observability** - OpenTelemetry integration, connection diagnostics, query profiling, change detection.

**Enterprise** - Multi-tenant session isolation, audit trail access, alert management, company info API.

**Operations** - Artisan commands for status, health checks, session management, and pool administration.

**Testing** - `SapB1Fake` trait, `FakeResponse` builder, entity factories for BusinessPartner, Item, and Order.

## Quick Examples

### Multiple Connections

```php
// Use different SAP B1 servers
$response = SapB1::connection('production')->get('Items');
$response = sap_b1('staging')->get('Items');
```

### Batch Operations

```php
$batch = SapB1::batch();
$batch->get('BusinessPartners', 'C001');
$batch->beginChangeset();
$batch->post('Orders', $orderData);
$batch->patch('Items', 'A001', ['ItemName' => 'Updated']);
$batch->endChangeset();
$responses = $batch->execute();
```

### OData v4 Support

```php
// SAP deprecated OData v3 in FP 2405
$response = SapB1::useODataV4()->get('Items');
```

### Change Detection

```php
// Watch for order changes (alternative to webhooks)
$detector = SapB1::changes();
$detector->watch('Orders')
    ->track('DocStatus', 'DocTotal')
    ->onCreated(fn($order) => dispatch(new NewOrderJob($order)))
    ->onUpdated(fn($order, $changes) => Log::info('Order updated', $changes));

$changes = $detector->poll(); // Run periodically
```

### Schema Discovery

```php
// Introspect SAP B1 entities
$entities = SapB1::metadata()->entities();
$schema = SapB1::metadata()->entity('BusinessPartners');
$udfs = SapB1::metadata()->udfs('OCRD'); // User Defined Fields
```

### Multi-Tenant

```php
// Tenant-specific connections
app(TenantManager::class)->forTenant('tenant-123', function() {
    return SapB1::get('Orders')->value();
});
```

### Health Monitoring

```bash
php artisan sap-b1:status --test
php artisan sap-b1:health --all
php artisan sap-b1:pool status
```

## Requirements

- PHP 8.4+
- Laravel 11.x or 12.x
- SAP Business One with Service Layer

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG](CHANGELOG.md) for recent changes.

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

Report vulnerabilities via [security policy](../../security/policy).

## Credits

- [Ismail Dasci](https://github.com/ismaildasci)
- [All Contributors](../../contributors)

## License

MIT License. See [LICENSE](LICENSE.md).
