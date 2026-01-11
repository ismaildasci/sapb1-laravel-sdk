# Quick Start

This guide will help you make your first API call to SAP Business One in under 5 minutes.

## Prerequisites

1. Installed and configured the package (see [Installation](installation.md))
2. SAP B1 Service Layer running and accessible
3. Valid credentials configured in `.env`

## Your First Query

```php
use SapB1\Facades\SapB1;

// Get all business partners
$response = SapB1::get('BusinessPartners');
$partners = $response->value();

// Get a specific partner
$partner = SapB1::find('BusinessPartners', 'C001');
echo $partner->entity()['CardName'];
```

## Using the Helper Function

```php
// Alternative to facade
$items = sap_b1()->get('Items')->value();
```

## Basic CRUD Operations

### Create

```php
$response = SapB1::create('BusinessPartners', [
    'CardCode' => 'C00001',
    'CardName' => 'New Customer',
    'CardType' => 'cCustomer',
]);

if ($response->successful()) {
    $newPartner = $response->entity();
}
```

### Read

```php
// Get all
$response = SapB1::get('Items');

// Get one
$response = SapB1::find('Items', 'A00001');

// Check existence
if (SapB1::exists('Items', 'A00001')) {
    // Item exists
}
```

### Update

```php
$response = SapB1::update('BusinessPartners', 'C00001', [
    'CardName' => 'Updated Customer Name',
    'Phone1' => '+90 555 123 4567',
]);
```

### Delete

```php
$response = SapB1::delete('BusinessPartners', 'C00001');
```

## Filtering with OData

```php
use SapB1\Facades\SapB1;

$response = SapB1::query()
    ->select('CardCode', 'CardName', 'Balance')
    ->where('CardType', 'cCustomer')
    ->where('Balance', 'gt', 1000)
    ->orderByDesc('Balance')
    ->top(10)
    ->get('BusinessPartners');

foreach ($response->value() as $customer) {
    echo "{$customer['CardName']}: {$customer['Balance']}\n";
}
```

## Creating a Sales Order

```php
$response = SapB1::create('Orders', [
    'CardCode' => 'C00001',
    'DocDueDate' => now()->format('Y-m-d'),
    'DocumentLines' => [
        [
            'ItemCode' => 'A00001',
            'Quantity' => 5,
            'UnitPrice' => 100,
        ],
        [
            'ItemCode' => 'A00002',
            'Quantity' => 3,
            'UnitPrice' => 250,
        ],
    ],
]);

if ($response->successful()) {
    $order = $response->entity();
    echo "Order created: DocEntry {$order['DocEntry']}";
}
```

## Error Handling

```php
use SapB1\Exceptions\ServiceLayerException;
use SapB1\Exceptions\AuthenticationException;

try {
    $response = SapB1::create('Orders', $data);
} catch (AuthenticationException $e) {
    // Handle login failures
    Log::error('SAP B1 authentication failed', ['error' => $e->getMessage()]);
} catch (ServiceLayerException $e) {
    // Handle SAP B1 errors
    Log::error('SAP B1 error', [
        'message' => $e->getMessage(),
        'code' => $e->getSapCode(),
        'suggestion' => $e->getSuggestion(),
    ]);
}
```

## Using the Service Endpoint

For a more fluent API:

```php
// Fluent service syntax
$partner = SapB1::service('BusinessPartners')
    ->find('C001')
    ->entity();

$orders = SapB1::service('Orders')
    ->select('DocEntry', 'DocNum', 'DocTotal')
    ->where('CardCode', 'C001')
    ->top(5)
    ->get()
    ->value();
```

## Next Steps

- [Making Requests](requests.md) - Learn about all request methods
- [OData Query Builder](odata-query-builder.md) - Master filtering and querying
- [Working with Responses](responses.md) - Understand response handling
- [Error Handling](error-handling.md) - Handle errors gracefully
