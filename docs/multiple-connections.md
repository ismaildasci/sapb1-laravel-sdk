# Multiple Connections

The SDK supports multiple SAP B1 connections, allowing you to work with different SAP instances or companies within the same application.

## Configuration

Define multiple connections in `config/sap-b1.php`:

```php
'connections' => [
    'default' => [
        'base_url' => env('SAP_B1_URL'),
        'company_db' => env('SAP_B1_COMPANY_DB'),
        'username' => env('SAP_B1_USERNAME'),
        'password' => env('SAP_B1_PASSWORD'),
        'language' => 23,
    ],

    'production' => [
        'base_url' => env('SAP_B1_PROD_URL'),
        'company_db' => env('SAP_B1_PROD_COMPANY_DB'),
        'username' => env('SAP_B1_PROD_USERNAME'),
        'password' => env('SAP_B1_PROD_PASSWORD'),
        'language' => 23,
    ],

    'warehouse' => [
        'base_url' => env('SAP_B1_WH_URL'),
        'company_db' => env('SAP_B1_WH_COMPANY_DB'),
        'username' => env('SAP_B1_WH_USERNAME'),
        'password' => env('SAP_B1_WH_PASSWORD'),
        'language' => 23,
    ],
],
```

## Environment Variables

```env
# Default connection
SAP_B1_URL=https://dev-server:50000
SAP_B1_COMPANY_DB=SBODEMOUS
SAP_B1_USERNAME=manager
SAP_B1_PASSWORD=dev_password

# Production connection
SAP_B1_PROD_URL=https://prod-server:50000
SAP_B1_PROD_COMPANY_DB=SBOPROD
SAP_B1_PROD_USERNAME=api_user
SAP_B1_PROD_PASSWORD=prod_password

# Warehouse connection
SAP_B1_WH_URL=https://wh-server:50000
SAP_B1_WH_COMPANY_DB=SBOWH
SAP_B1_WH_USERNAME=wh_user
SAP_B1_WH_PASSWORD=wh_password
```

## Switching Connections

### Using the Facade

```php
use SapB1\Facades\SapB1;

// Use default connection
$defaultPartners = SapB1::get('BusinessPartners');

// Switch to production
$prodPartners = SapB1::connection('production')->get('BusinessPartners');

// Switch to warehouse
$whItems = SapB1::connection('warehouse')->get('Items');
```

### Using the Helper

```php
// Default connection
$partners = sap_b1()->get('BusinessPartners');

// Specific connection
$prodOrders = sap_b1()->connection('production')->get('Orders');
```

### Using Dependency Injection

```php
use SapB1\Client\SapB1Client;

class MultiCompanyService
{
    public function __construct(
        protected SapB1Client $client
    ) {}

    public function syncFromProduction(): void
    {
        $prodClient = $this->client->connection('production');
        $defaultClient = $this->client->connection('default');

        $prodPartners = $prodClient->get('BusinessPartners')->value();

        foreach ($prodPartners as $partner) {
            $defaultClient->create('BusinessPartners', $partner);
        }
    }
}
```

## Connection-Specific Operations

### Full CRUD Example

```php
use SapB1\Facades\SapB1;

// Create in production
$response = SapB1::connection('production')->create('BusinessPartners', [
    'CardCode' => 'C00001',
    'CardName' => 'New Customer',
]);

// Read from warehouse
$items = SapB1::connection('warehouse')
    ->query()
    ->where('QuantityOnStock', 'gt', 0)
    ->get('Items');

// Update in default
SapB1::connection('default')->update('BusinessPartners', 'C001', [
    'Phone1' => '+90 555 123 4567',
]);
```

### Chaining with Queries

```php
$highValueCustomers = SapB1::connection('production')
    ->query()
    ->select('CardCode', 'CardName', 'Balance')
    ->where('Balance', 'gt', 100000)
    ->orderByDesc('Balance')
    ->top(10)
    ->get('BusinessPartners');
```

## Default Connection

Set the default connection in your environment:

```env
SAP_B1_CONNECTION=production
```

Or in configuration:

```php
'default' => env('SAP_B1_CONNECTION', 'default'),
```

## Health Checks for Multiple Connections

### Check All Connections

```bash
php artisan sap-b1:health --all
```

### Check Specific Connection

```bash
php artisan sap-b1:health --connection=production
```

### Programmatic Health Check

```php
use SapB1\Health\SapB1HealthCheck;

class HealthController
{
    public function check(SapB1HealthCheck $healthCheck)
    {
        // Check all
        $results = $healthCheck->checkAll();

        // Check specific connections
        $results = $healthCheck->checkAll(['production', 'warehouse']);

        // Check single
        $result = $healthCheck->check('production');

        return response()->json([
            'healthy' => $healthCheck->isHealthy(),
            'connections' => array_map(fn($r) => $r->toArray(), $results),
        ]);
    }
}
```

## Session Management

Each connection maintains its own session:

```php
// Check session for specific connection
$hasSession = SapB1::connection('production')->hasValidSession();

// Refresh specific connection session
SapB1::connection('production')->refreshSession();

// Logout from specific connection
SapB1::connection('production')->logout();
```

### Artisan Commands

```bash
# Session management for specific connection
php artisan sap-b1:session login --connection=production
php artisan sap-b1:session logout --connection=production
php artisan sap-b1:session refresh --connection=production

# Status for specific connection
php artisan sap-b1:status --connection=production --test
```

## Session Pool per Connection

Configure pool settings per connection:

```php
'pool' => [
    'enabled' => true,
    'connections' => [
        'default' => [
            'min_size' => 2,
            'max_size' => 10,
        ],
        'production' => [
            'min_size' => 5,
            'max_size' => 20,
        ],
        'warehouse' => [
            'min_size' => 1,
            'max_size' => 5,
        ],
    ],
],
```

## Best Practices

### 1. Use Constants or Enums

```php
class SapConnection
{
    public const DEFAULT = 'default';
    public const PRODUCTION = 'production';
    public const WAREHOUSE = 'warehouse';
}

$orders = SapB1::connection(SapConnection::PRODUCTION)->get('Orders');
```

### 2. Create Dedicated Services

```php
class ProductionSapService
{
    public function __construct(
        protected SapB1Client $client
    ) {}

    protected function connection(): SapB1Client
    {
        return $this->client->connection('production');
    }

    public function getOrders(): array
    {
        return $this->connection()->get('Orders')->value();
    }
}
```

### 3. Environment-Based Defaults

```php
// In AppServiceProvider
$this->app->bind('sap.production', function ($app) {
    return $app->make(SapB1Client::class)->connection('production');
});

// Usage
$prodClient = app('sap.production');
```

## Next Steps

- [Health Checks](health-checks.md) - Monitor connection health
- [Session Pool](session-pool.md) - High-concurrency setup
- [Configuration](configuration.md) - All configuration options
