# Batch Operations

Execute multiple operations in a single HTTP request for better performance.

## Basic Usage

```php
use SapB1\Facades\SapB1;

$batch = SapB1::batch();

$batch->get('BusinessPartners', 'C001');
$batch->get('Items', 'A001');
$batch->post('Orders', $orderData);

$responses = $batch->execute();
```

## Changesets (Atomic Transactions)

Operations within a changeset are atomic - all succeed or all fail:

```php
$batch = SapB1::batch();

// These operations are atomic
$batch->beginChangeset();
$batch->post('Orders', $orderData);
$batch->patch('BusinessPartners', 'C001', ['Phone1' => '555-1234']);
$batch->endChangeset();

// This runs independently
$batch->get('Items', 'A001');

$responses = $batch->execute();
```

## Working with Responses

```php
$responses = $batch->execute();

foreach ($responses as $index => $response) {
    if ($response->successful()) {
        $data = $response->json();
    } else {
        $error = $response->errorMessage();
    }
}
```

## Mixed Operations

```php
$batch = SapB1::batch();

// Read operations
$batch->get('BusinessPartners', 'C001');
$batch->get('Items', 'A001');

// Write operations in changeset
$batch->beginChangeset();
$batch->post('Orders', [
    'CardCode' => 'C001',
    'DocumentLines' => [
        ['ItemCode' => 'A001', 'Quantity' => 5]
    ]
]);
$batch->endChangeset();

$responses = $batch->execute();
```

## Error Handling

```php
use SapB1\Exceptions\BatchException;

try {
    $responses = $batch->execute();
} catch (BatchException $e) {
    // Handle batch-level errors
    $failedIndex = $e->getFailedIndex();
    $context = $e->getContext();
}
```

Next: [Session Pool](session-pool.md)
