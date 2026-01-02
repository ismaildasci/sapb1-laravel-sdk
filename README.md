# SAP Business One Laravel SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ismaildasci/laravel-sapb1.svg?style=flat-square)](https://packagist.org/packages/ismaildasci/laravel-sapb1)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/ismaildasci/laravel-sapb1/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/ismaildasci/laravel-sapb1/actions?query=workflow%3Arun-tests+branch%3Amain)
[![PHPStan](https://img.shields.io/github/actions/workflow/status/ismaildasci/laravel-sapb1/phpstan.yml?branch=main&label=phpstan&style=flat-square)](https://github.com/ismaildasci/laravel-sapb1/actions?query=workflow%3Aphpstan+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/ismaildasci/laravel-sapb1.svg?style=flat-square)](https://packagist.org/packages/ismaildasci/laravel-sapb1)

A powerful Laravel package for seamless integration with SAP Business One Service Layer API. Features include automatic session management, OData query builder, multiple connection support, and comprehensive testing utilities.

## Requirements

- PHP 8.4+
- Laravel 11.x or 12.x
- SAP Business One with Service Layer enabled

## Installation

Install the package via Composer:

```bash
composer require ismaildasci/laravel-sapb1
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="sap-b1-config"
```

If using database session driver, publish and run migrations:

```bash
php artisan vendor:publish --tag="sap-b1-migrations"
php artisan migrate
```

## Configuration

Add the following environment variables to your `.env` file:

```env
SAP_B1_URL=https://your-sap-server:50000
SAP_B1_COMPANY_DB=YOUR_COMPANY_DB
SAP_B1_USERNAME=manager
SAP_B1_PASSWORD=your_password
SAP_B1_SESSION_DRIVER=file
```

### Configuration Options

```php
// config/sap-b1.php
return [
    'default' => env('SAP_B1_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            'base_url' => env('SAP_B1_URL'),
            'company_db' => env('SAP_B1_COMPANY_DB'),
            'username' => env('SAP_B1_USERNAME'),
            'password' => env('SAP_B1_PASSWORD'),
            'language' => env('SAP_B1_LANGUAGE', 23),
        ],
    ],

    'session' => [
        'driver' => env('SAP_B1_SESSION_DRIVER', 'file'), // file, redis, database
        'ttl' => 1680,
        'refresh_threshold' => 300,
    ],

    'http' => [
        'timeout' => 30,
        'verify' => env('SAP_B1_VERIFY_SSL', true),
        'retry' => [
            'times' => 3,
            'sleep' => 1000,
        ],
    ],
];
```

## Basic Usage

### Using the Facade

```php
use SapB1\Facades\SapB1;

// Get all business partners
$response = SapB1::get('BusinessPartners');
$partners = $response->value();

// Find a specific business partner
$response = SapB1::find('BusinessPartners', 'C001');
$partner = $response->entity();

// Create a new business partner
$response = SapB1::create('BusinessPartners', [
    'CardCode' => 'C002',
    'CardName' => 'New Customer',
    'CardType' => 'cCustomer',
]);

// Update a business partner
$response = SapB1::update('BusinessPartners', 'C002', [
    'CardName' => 'Updated Customer Name',
]);

// Delete a business partner
$response = SapB1::delete('BusinessPartners', 'C002');
```

### Using the Helper Function

```php
// Get client instance
$client = sap_b1();

// Use specific connection
$client = sap_b1('secondary');

// Chain methods
$response = sap_b1()->get('Items');
```

### Using Dependency Injection

```php
use SapB1\Client\SapB1Client;

class BusinessPartnerController extends Controller
{
    public function __construct(
        protected SapB1Client $sapB1
    ) {}

    public function index()
    {
        $response = $this->sapB1->get('BusinessPartners');
        return $response->value();
    }
}
```

## OData Query Builder

Build complex queries with the fluent OData query builder:

```php
use SapB1\Facades\SapB1;
use SapB1\Client\ODataBuilder;

// Simple query
$response = SapB1::query()
    ->select('CardCode', 'CardName', 'Phone1')
    ->where('CardType', 'cCustomer')
    ->where('Frozen', 'tNO')
    ->orderBy('CardName')
    ->top(10);

$partners = SapB1::get('BusinessPartners');

// Advanced filtering
$query = ODataBuilder::make()
    ->select('DocEntry', 'DocNum', 'CardCode', 'DocTotal')
    ->where('DocDate', 'ge', '2024-01-01')
    ->whereBetween('DocTotal', 1000, 50000)
    ->whereContains('CardName', 'Corp')
    ->orderByDesc('DocDate')
    ->expand('DocumentLines')
    ->page(1, 20)
    ->inlineCount();

$response = SapB1::withOData($query)->get('Orders');

// Get count
$totalCount = $response->count();

// Check for more pages
if ($response->hasNextPage()) {
    $nextResponse = SapB1::nextPage($response);
}
```

### Available Query Methods

| Method | Description |
|--------|-------------|
| `select(...$fields)` | Select specific fields |
| `filter($expression)` | Raw OData filter |
| `where($field, $operator, $value)` | Add filter condition |
| `whereIn($field, $values)` | Filter by multiple values |
| `whereContains($field, $value)` | Contains filter |
| `whereStartsWith($field, $value)` | Starts with filter |
| `whereNull($field)` | Null check |
| `whereBetween($field, $start, $end)` | Between filter |
| `orderBy($field, $direction)` | Order results |
| `orderByDesc($field)` | Order descending |
| `top($count)` / `limit($count)` | Limit results |
| `skip($count)` / `offset($count)` | Skip results |
| `page($page, $perPage)` | Pagination |
| `expand(...$relations)` | Expand related entities |
| `inlineCount()` | Include total count |

## Working with Responses

```php
$response = SapB1::get('BusinessPartners');

// Check response status
$response->successful();  // true if 2xx
$response->failed();      // true if 4xx or 5xx
$response->status();      // HTTP status code

// Get data
$response->value();       // Array of entities
$response->entity();      // Single entity
$response->json('key');   // Specific JSON key

// OData metadata
$response->count();       // @odata.count
$response->nextLink();    // @odata.nextLink
$response->hasNextPage(); // Check if more pages exist

// Error handling
if ($response->hasError()) {
    $message = $response->errorMessage();
    $code = $response->errorCode();
}
```

## Multiple Connections

Configure multiple SAP B1 connections:

```php
// config/sap-b1.php
'connections' => [
    'default' => [...],
    'secondary' => [
        'base_url' => env('SAP_B1_SECONDARY_URL'),
        'company_db' => env('SAP_B1_SECONDARY_DB'),
        'username' => env('SAP_B1_SECONDARY_USER'),
        'password' => env('SAP_B1_SECONDARY_PASS'),
    ],
],
```

Use different connections:

```php
// Using facade
$response = SapB1::connection('secondary')->get('Items');

// Using helper
$response = sap_b1('secondary')->get('Items');

// Using DI
$client->connection('secondary')->get('Items');
```

## Session Management

Sessions are managed automatically. You can also manage them manually:

```php
use SapB1\Session\SessionManager;

$sessionManager = app(SessionManager::class);

// Check session status
$sessionManager->hasValidSession('default');

// Force refresh
$sessionManager->refreshSession('default');

// Logout
$sessionManager->logout('default');

// Clear all sessions
$sessionManager->clearAllSessions();
```

### Session Drivers

**File Driver** (default):
```env
SAP_B1_SESSION_DRIVER=file
```

**Redis Driver**:
```env
SAP_B1_SESSION_DRIVER=redis
SAP_B1_REDIS_CONNECTION=default
```

**Database Driver**:
```env
SAP_B1_SESSION_DRIVER=database
```

## Artisan Commands

```bash
# Check connection status
php artisan sap-b1:status
php artisan sap-b1:status --connection=secondary
php artisan sap-b1:status --test

# Manage sessions
php artisan sap-b1:session login
php artisan sap-b1:session logout
php artisan sap-b1:session refresh
php artisan sap-b1:session clear
php artisan sap-b1:session clear-all

# Health check
php artisan sap-b1:health
php artisan sap-b1:health --all
php artisan sap-b1:health --json
```

## Health Checks

Monitor SAP B1 connection health:

```php
use SapB1\Health\SapB1HealthCheck;

$healthCheck = app(SapB1HealthCheck::class);

// Check single connection
$result = $healthCheck->check('default');

if ($result->isHealthy()) {
    echo "Response time: {$result->responseTime}ms";
    echo "Company: {$result->companyDb}";
}

// Check all connections
$results = $healthCheck->checkAll();

// Quick health check
if ($healthCheck->isHealthy()) {
    // All connections are healthy
}
```

## Testing

The package provides testing utilities for mocking SAP B1 requests:

```php
use SapB1\Testing\SapB1Fake;
use SapB1\Testing\FakeResponse;
use SapB1\Testing\Factories\BusinessPartnerFactory;

class BusinessPartnerTest extends TestCase
{
    use SapB1Fake;

    public function test_can_get_business_partners(): void
    {
        $this->fakeSapB1([
            'BusinessPartners' => FakeResponse::collection([
                BusinessPartnerFactory::new()->make(),
                BusinessPartnerFactory::new()->supplier()->make(),
            ]),
        ]);

        $response = SapB1::get('BusinessPartners');

        $this->assertCount(2, $response->value());
        $this->assertSapB1Get('BusinessPartners');
    }

    public function test_can_create_business_partner(): void
    {
        $this->fakeSapB1([
            'BusinessPartners' => FakeResponse::entity([
                'CardCode' => 'C001',
            ]),
        ]);

        SapB1::create('BusinessPartners', [
            'CardCode' => 'C001',
            'CardName' => 'Test Customer',
        ]);

        $this->assertSapB1Post('BusinessPartners', [
            'CardCode' => 'C001',
        ]);
    }
}
```

### Available Factories

```php
use SapB1\Testing\Factories\BusinessPartnerFactory;
use SapB1\Testing\Factories\ItemFactory;
use SapB1\Testing\Factories\OrderFactory;

// Business Partner
BusinessPartnerFactory::new()->make();
BusinessPartnerFactory::new()->supplier()->make();
BusinessPartnerFactory::new()->frozen()->withAddress()->make();

// Item
ItemFactory::new()->make();
ItemFactory::new()->service()->make();
ItemFactory::new()->withPrice(99.99)->withQuantity(100)->make();

// Order
OrderFactory::new()->make();
OrderFactory::new()->withRandomLines(5)->make();
OrderFactory::new()->forCustomer('C001')->closed()->make();
```

## Error Handling

```php
use SapB1\Exceptions\AuthenticationException;
use SapB1\Exceptions\ConnectionException;
use SapB1\Exceptions\ServiceLayerException;
use SapB1\Exceptions\SessionExpiredException;

try {
    $response = SapB1::create('BusinessPartners', $data);
} catch (AuthenticationException $e) {
    // Invalid credentials
} catch (SessionExpiredException $e) {
    // Session expired, will auto-refresh
} catch (ConnectionException $e) {
    // Network/connection issues
} catch (ServiceLayerException $e) {
    // SAP B1 API error
    $statusCode = $e->getStatusCode();
    $sapCode = $e->getSapCode();
    $context = $e->getContext();
}
```

## Events

The package dispatches events for monitoring and logging:

```php
use SapB1\Events\SessionCreated;
use SapB1\Events\SessionExpired;
use SapB1\Events\RequestSending;
use SapB1\Events\RequestSent;
use SapB1\Events\RequestFailed;

// In EventServiceProvider
protected $listen = [
    SessionCreated::class => [LogSessionCreated::class],
    RequestSent::class => [LogApiRequest::class],
];
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Ismail Dasci](https://github.com/ismaildasci)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
