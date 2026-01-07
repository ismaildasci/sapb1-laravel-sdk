# Configuration

The configuration file is located at `config/sap-b1.php` after publishing.

## Connection Settings

```php
'connections' => [
    'default' => [
        'base_url' => env('SAP_B1_URL'),
        'company_db' => env('SAP_B1_COMPANY_DB'),
        'username' => env('SAP_B1_USERNAME'),
        'password' => env('SAP_B1_PASSWORD'),
        'language' => env('SAP_B1_LANGUAGE', 23),
        'odata_version' => env('SAP_B1_ODATA_VERSION', 'v1'), // 'v1' or 'v2'
    ],
],
```

## Session Management

```php
'session' => [
    'driver' => env('SAP_B1_SESSION_DRIVER', 'file'), // file, redis, database
    'ttl' => 1680,
    'refresh_threshold' => 300,
    'auto_refresh' => true,
],
```

Available drivers:
- `file` - Local filesystem (default)
- `redis` - Redis server
- `database` - Database table

## HTTP Client

```php
'http' => [
    'timeout' => 30,
    'connect_timeout' => 10,
    'verify' => env('SAP_B1_VERIFY_SSL', true),

    'retry' => [
        'times' => 3,
        'sleep' => 1000,
        'exponential_backoff' => true,
    ],

    'compression' => [
        'enabled' => false,
        'min_size' => 1024,
    ],

    'circuit_breaker' => [
        'enabled' => false,
        'failure_threshold' => 5,
        'open_duration' => 30,
    ],
],
```

## Query Caching

```php
'cache' => [
    'enabled' => env('SAP_B1_CACHE_ENABLED', false),
    'ttl' => 300,
    'include' => ['Items', 'BusinessPartners'],
    'exclude' => ['Login', 'Logout'],
],
```

## Session Pool

For high-concurrency scenarios:

```php
'pool' => [
    'enabled' => env('SAP_B1_POOL_ENABLED', false),
    'warmup_on_boot' => true,
    'connections' => [
        'default' => [
            'min_size' => 2,
            'max_size' => 10,
            'idle_timeout' => 600,
            'wait_timeout' => 30,
        ],
    ],
    'algorithm' => 'round_robin', // round_robin, least_connections, lifo
],
```

## Attachments

```php
'attachments' => [
    'max_size' => 10 * 1024 * 1024,
    'allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', ...],
],
```

## Query Profiling

```php
'profiling' => [
    'enabled' => env('SAP_B1_PROFILING', false),
    'slow_query_threshold' => 1000,
],
```

Next: [Quick Start](quick-start.md)
