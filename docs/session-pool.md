# Session Pool

Session pooling maintains multiple SAP B1 sessions for high-concurrency scenarios, preventing bottlenecks when many requests hit simultaneously.

## When to Use

- High-traffic applications with concurrent SAP requests
- Queue workers processing many jobs in parallel
- API endpoints serving multiple simultaneous users

For most applications, the default single-session approach is sufficient.

## Enable Session Pool

```env
SAP_B1_POOL_ENABLED=true
```

```php
// config/sap-b1.php
'pool' => [
    'enabled' => true,
    'warmup_on_boot' => true,
    'connections' => [
        'default' => [
            'min_size' => 2,
            'max_size' => 10,
            'idle_timeout' => 600,
            'wait_timeout' => 30,
        ],
    ],
    'algorithm' => 'round_robin',
],
```

## Distribution Algorithms

| Algorithm | Best For |
|-----------|----------|
| `round_robin` | Even distribution across sessions |
| `least_connections` | Balancing load based on usage |
| `lifo` | Cache locality optimization |

## Manual Session Management

```php
use SapB1\Facades\SapB1;

// Sessions are automatically acquired and released
$response = SapB1::get('BusinessPartners');

// Check if pool is active
if (SapB1::isUsingPool()) {
    $stats = SapB1::getPoolStats();
}
```

## Artisan Commands

```bash
# Pool status and statistics
php artisan sap-b1:pool status

# Pre-create sessions
php artisan sap-b1:pool warmup --count=5

# Remove all sessions
php artisan sap-b1:pool drain

# Clean expired sessions
php artisan sap-b1:pool cleanup

# List sessions
php artisan sap-b1:pool sessions
```

## Pool Events

```php
use SapB1\Events\SessionAcquired;
use SapB1\Events\SessionReleased;
use SapB1\Events\PoolWarmedUp;
use SapB1\Events\PoolSessionExpired;
```

## Storage Drivers

Pool state can be stored in Redis or database. Redis is recommended for multi-server deployments.

Next: [Circuit Breaker](circuit-breaker.md)
