<?php

declare(strict_types=1);

/**
 * Batch Operations with SAP Business One Laravel SDK
 *
 * Batch operations allow you to execute multiple requests in a single HTTP call,
 * improving performance and enabling atomic transactions via changesets.
 */

use SapB1\Facades\SapB1;

// =============================================================================
// BASIC BATCH OPERATIONS
// =============================================================================

$batch = SapB1::batch();

// Add GET requests to the batch
$batch->get('BusinessPartners', 'C001');
$batch->get('BusinessPartners', 'C002');
$batch->get('Items', 'A001');

// Execute the batch
$responses = $batch->execute();

// Process responses
foreach ($responses as $index => $response) {
    if ($response->successful()) {
        echo "Request {$index}: Success" . PHP_EOL;
        print_r($response->value());
    } else {
        echo "Request {$index}: Failed - " . $response->status() . PHP_EOL;
    }
}

// =============================================================================
// BATCH WITH CHANGESET (ATOMIC TRANSACTION)
// =============================================================================

// Changesets ensure all operations succeed or all fail (atomic)
$batch = SapB1::batch();

// Start a changeset - all operations inside are atomic
$batch->beginChangeset();

// Create a business partner
$batch->post('BusinessPartners', [
    'CardCode' => 'C100',
    'CardName' => 'New Customer Inc',
    'CardType' => 'cCustomer',
]);

// Create an order for the new partner
$batch->post('Orders', [
    'CardCode' => 'C100',
    'DocDate' => date('Y-m-d'),
    'DocumentLines' => [
        ['ItemCode' => 'A001', 'Quantity' => 5],
    ],
]);

// End the changeset
$batch->endChangeset();

// Execute - if any operation fails, all are rolled back
$responses = $batch->execute();

// =============================================================================
// MIXED OPERATIONS IN BATCH
// =============================================================================

$batch = SapB1::batch();

// Read operations (outside changeset - don't affect atomicity)
$batch->get('Items', 'A001');

// Write operations in changeset
$batch->beginChangeset();

$batch->post('BusinessPartners', [
    'CardCode' => 'C101',
    'CardName' => 'Another Customer',
    'CardType' => 'cCustomer',
]);

$batch->patch('Items', 'A001', [
    'ItemName' => 'Updated Item Name',
]);

$batch->endChangeset();

// More read operations
$batch->get('BusinessPartners', 'C001');

$responses = $batch->execute();

// =============================================================================
// BULK UPDATE WITH BATCH
// =============================================================================

// Update multiple items at once
$itemsToUpdate = [
    ['code' => 'A001', 'price' => 99.99],
    ['code' => 'A002', 'price' => 149.99],
    ['code' => 'A003', 'price' => 199.99],
    ['code' => 'A004', 'price' => 249.99],
];

$batch = SapB1::batch();
$batch->beginChangeset();

foreach ($itemsToUpdate as $item) {
    $batch->patch('Items', $item['code'], [
        'SalesUnitPrice' => $item['price'],
    ]);
}

$batch->endChangeset();
$responses = $batch->execute();

// Check for errors
$hasErrors = false;
foreach ($responses as $response) {
    if (!$response->successful()) {
        $hasErrors = true;
        echo "Error: " . $response->json()['error']['message']['value'] ?? 'Unknown error' . PHP_EOL;
    }
}

if (!$hasErrors) {
    echo "All items updated successfully!" . PHP_EOL;
}

// =============================================================================
// BATCH FOR DATA IMPORT
// =============================================================================

// Import multiple business partners from CSV/external source
$partnersToImport = [
    ['code' => 'C200', 'name' => 'Partner A', 'email' => 'a@example.com'],
    ['code' => 'C201', 'name' => 'Partner B', 'email' => 'b@example.com'],
    ['code' => 'C202', 'name' => 'Partner C', 'email' => 'c@example.com'],
];

$batch = SapB1::batch();
$batch->beginChangeset();

foreach ($partnersToImport as $partner) {
    $batch->post('BusinessPartners', [
        'CardCode' => $partner['code'],
        'CardName' => $partner['name'],
        'EmailAddress' => $partner['email'],
        'CardType' => 'cCustomer',
    ]);
}

$batch->endChangeset();

try {
    $responses = $batch->execute();
    echo "Imported " . count($partnersToImport) . " partners successfully!" . PHP_EOL;
} catch (\SapB1\Exceptions\ServiceLayerException $e) {
    echo "Import failed: " . $e->getHumanMessage() . PHP_EOL;
    echo "Suggestion: " . $e->getSuggestion() . PHP_EOL;
}

// =============================================================================
// DELETE OPERATIONS IN BATCH
// =============================================================================

$batch = SapB1::batch();
$batch->beginChangeset();

// Delete multiple records
$batch->delete('BusinessPartners', 'C200');
$batch->delete('BusinessPartners', 'C201');
$batch->delete('BusinessPartners', 'C202');

$batch->endChangeset();
$responses = $batch->execute();
