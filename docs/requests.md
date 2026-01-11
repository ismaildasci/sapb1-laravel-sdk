# Making Requests

This guide covers all methods for making requests to the SAP B1 Service Layer.

## Entry Points

There are three ways to access the SAP B1 client:

```php
// 1. Using the Facade (recommended)
use SapB1\Facades\SapB1;
$response = SapB1::get('BusinessPartners');

// 2. Using the helper function
$response = sap_b1()->get('BusinessPartners');

// 3. Using dependency injection
use SapB1\Client\SapB1Client;

class MyController
{
    public function index(SapB1Client $client)
    {
        return $client->get('BusinessPartners');
    }
}
```

## CRUD Methods

### GET - Retrieve Records

```php
// Get all records
$response = SapB1::get('BusinessPartners');

// Get with OData query
$response = SapB1::query()
    ->where('CardType', 'cCustomer')
    ->top(10)
    ->get('BusinessPartners');
```

### FIND - Retrieve Single Record

```php
// String key
$response = SapB1::find('BusinessPartners', 'C001');

// Numeric key
$response = SapB1::find('Orders', 123);

// Composite key
$response = SapB1::find('DocumentLines', [
    'DocEntry' => 123,
    'LineNum' => 0,
]);
```

### CREATE - Create New Record

```php
$response = SapB1::create('BusinessPartners', [
    'CardCode' => 'C00001',
    'CardName' => 'New Customer',
    'CardType' => 'cCustomer',
    'GroupCode' => 100,
]);
```

### UPDATE - Modify Existing Record

```php
$response = SapB1::update('BusinessPartners', 'C001', [
    'CardName' => 'Updated Name',
    'Phone1' => '+90 555 123 4567',
]);
```

### DELETE - Remove Record

```php
$response = SapB1::delete('BusinessPartners', 'C001');
```

## Helper Methods

### Check Existence

```php
if (SapB1::exists('Items', 'A00001')) {
    // Item exists
}
```

### Get Count

```php
$count = SapB1::query()
    ->where('CardType', 'cCustomer')
    ->count('BusinessPartners');

echo "Total customers: {$count}";
```

## SAP B1 Actions

Call SAP B1 document actions:

```php
// Close an order
$response = SapB1::action('Orders', 123, 'Close');

// Cancel a document
$response = SapB1::action('Orders', 123, 'Cancel');

// Action with parameters
$response = SapB1::action('Quotations', 456, 'CreateSalesOrder', [
    'DeliveryDate' => '2026-01-20',
]);
```

## Raw HTTP Methods

For advanced use cases:

```php
// POST
$response = SapB1::post('Orders', $data);

// PUT (full replacement)
$response = SapB1::put('BusinessPartners(\'C001\')', $fullData);

// PATCH (partial update)
$response = SapB1::patch('BusinessPartners(\'C001\')', $partialData);

// DELETE without key formatting
$response = SapB1::rawDelete('Orders(123)');
```

## Pagination

### Manual Pagination

```php
$response = SapB1::get('BusinessPartners');

while ($response !== null) {
    foreach ($response->value() as $partner) {
        // Process partner
    }

    $response = SapB1::nextPage($response);
}
```

### Using Generator

```php
foreach (SapB1::paginate('BusinessPartners') as $response) {
    foreach ($response->value() as $partner) {
        // Process partner
    }
}
```

### OData Pagination

```php
$response = SapB1::query()
    ->page(1, 20)  // Page 1, 20 items per page
    ->get('BusinessPartners');

// Or use skip/top
$response = SapB1::query()
    ->skip(40)
    ->top(20)
    ->get('BusinessPartners');
```

## Service Endpoint (Fluent API)

For a more expressive syntax:

```php
$client = SapB1::service('BusinessPartners');

// Find
$partner = $client->find('C001')->entity();

// Query
$customers = $client
    ->select('CardCode', 'CardName')
    ->where('CardType', 'cCustomer')
    ->top(10)
    ->get()
    ->value();

// Create
$response = $client->create([
    'CardCode' => 'C00001',
    'CardName' => 'New Customer',
]);

// Update
$response = $client->update('C001', [
    'CardName' => 'Updated Name',
]);

// Delete
$response = $client->delete('C001');
```

## OData Version

SAP B1 supports both OData v3 (v1 endpoint) and OData v4 (v2 endpoint):

```php
// Use OData v4 (Service Layer v2)
$response = SapB1::useODataV4()->get('BusinessPartners');

// Use OData v3 (Service Layer v1) - default
$response = SapB1::useODataV3()->get('BusinessPartners');

// Configure in .env
// SAP_B1_ODATA_VERSION=v2
```

## Session Management

```php
// Check session validity
if (SapB1::hasValidSession()) {
    // Session is active
}

// Manually refresh session
SapB1::refreshSession();

// Logout
SapB1::logout();
```

### Disable Auto Refresh

```php
// Disable automatic session refresh on 401
$response = SapB1::withoutAutoRefresh()->get('Orders');
```

## Request Options

### Timeouts

Configured globally in `config/sap-b1.php`:

```php
'http' => [
    'timeout' => 30,           // Request timeout
    'connect_timeout' => 10,   // Connection timeout
],
```

### Retry Configuration

```php
'http' => [
    'retry' => [
        'times' => 3,
        'sleep' => 1000,
        'when' => [429, 500, 502, 503, 504],
    ],
],
```

## Next Steps

- [OData Query Builder](odata-query-builder.md) - Build complex queries
- [Working with Responses](responses.md) - Handle response data
- [Batch Operations](batch-operations.md) - Execute multiple operations
