# Query Caching

Cache GET request results to reduce load on SAP B1 and improve response times.

## Enable Caching

```env
SAP_B1_CACHE_ENABLED=true
```

```php
// config/sap-b1.php
'cache' => [
    'enabled' => true,
    'driver' => 'redis',
    'ttl' => 300,
    'prefix' => 'sap_b1_cache:',
    'include' => ['Items', 'BusinessPartners'],
    'exclude' => ['Login', 'Logout'],
],
```

## Pattern-Based Rules

```php
'include' => [
    'Items',           // Cache all Items requests
    'BusinessPartners', // Cache all BusinessPartners
    'PriceLists/*',    // Wildcard pattern
],
```

## Cache Invalidation

The SDK automatically invalidates related caches on write operations:

```php
use SapB1\Cache\CacheInvalidator;

$invalidator = app(CacheInvalidator::class);

// Manual invalidation
$invalidator->invalidate('BusinessPartners');

// Invalidate with relations
$invalidator->invalidateWithRelations('Orders');
```

## Per-Request Control

```php
use SapB1\Facades\SapB1;

// Skip cache for this request
$response = SapB1::withoutCache()->get('Items');

// Force cache refresh
$response = SapB1::refreshCache()->get('Items');
```

## Cache Statistics

```php
use SapB1\Cache\QueryCache;

$cache = app(QueryCache::class);
$stats = $cache->getStatistics();

// hits, misses, hit_rate
```

Next: [Attachments](attachments.md)
