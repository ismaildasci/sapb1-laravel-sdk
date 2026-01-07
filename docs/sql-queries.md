# SQL Queries

Execute stored SQL queries and access the Semantic Layer.

## Stored SQL Queries

Execute queries defined in SAP B1's Query Manager:

```php
use SapB1\Facades\SapB1;

$results = SapB1::sql('TopCustomersByRevenue')
    ->param('Year', 2024)
    ->param('MinAmount', 10000)
    ->execute();
```

## Pagination

```php
$results = SapB1::sql('AllOpenOrders')
    ->top(50)
    ->skip(100)
    ->execute();
```

## Semantic Layer

Query semantic layer views for analytics:

```php
$report = SapB1::semantic('SalesAnalysis')
    ->dimensions('CardCode', 'ItemCode', 'PostingDate')
    ->measures('Quantity', 'LineTotal')
    ->filter('PostingDate', 'ge', '2024-01-01')
    ->execute();
```

## Cross-Company Queries

Query across multiple company databases:

```php
// Query all companies
$results = SapB1::query()
    ->crossCompany('*')
    ->get('BusinessPartners');

// Query specific company
$results = SapB1::query()
    ->crossCompany('ANOTHER_COMPANY_DB')
    ->get('Items');
```

## Query Profiling

Monitor query performance:

```php
use SapB1\Profiling\QueryProfiler;

$profiler = app(QueryProfiler::class);

// Get slow queries
$slowQueries = $profiler->getSlowQueries();

// Get statistics by endpoint
$stats = $profiler->getStatistics('Orders');
```

Enable profiling in config:

```php
'profiling' => [
    'enabled' => true,
    'slow_query_threshold' => 1000, // ms
],
```

Next: [Health Checks](health-checks.md)
