<?php

declare(strict_types=1);

/**
 * Basic CRUD Operations with SAP Business One Laravel SDK
 *
 * This example demonstrates the fundamental operations:
 * - Creating records
 * - Reading single and multiple records
 * - Updating records
 * - Deleting records
 */

use SapB1\Facades\SapB1;

// =============================================================================
// READING DATA
// =============================================================================

// Get all business partners
$partners = SapB1::get('BusinessPartners')->value();

// Get a single business partner by key
$partner = SapB1::find('BusinessPartners', 'C001')->value();

// Get items with simple filter
$items = SapB1::query()
    ->where('ItemType', 'itItems')
    ->get('Items')
    ->value();

// =============================================================================
// CREATING RECORDS
// =============================================================================

// Create a new business partner
$newPartner = SapB1::create('BusinessPartners', [
    'CardCode' => 'C002',
    'CardName' => 'Acme Corporation',
    'CardType' => 'cCustomer',
    'EmailAddress' => 'contact@acme.com',
    'Phone1' => '+1-555-123-4567',
    'BPAddresses' => [
        [
            'AddressName' => 'Main Office',
            'Street' => '123 Business Ave',
            'City' => 'New York',
            'Country' => 'US',
            'AddressType' => 'bo_BillTo',
        ],
    ],
]);

// Create a new item
$newItem = SapB1::create('Items', [
    'ItemCode' => 'A001',
    'ItemName' => 'Widget Pro',
    'ItemType' => 'itItems',
    'ItemsGroupCode' => 100,
    'SalesItem' => 'tYES',
    'PurchaseItem' => 'tYES',
    'InventoryItem' => 'tYES',
]);

// Create a sales order with line items
$order = SapB1::create('Orders', [
    'CardCode' => 'C001',
    'DocDate' => date('Y-m-d'),
    'DocDueDate' => date('Y-m-d', strtotime('+30 days')),
    'DocumentLines' => [
        [
            'ItemCode' => 'A001',
            'Quantity' => 10,
            'UnitPrice' => 99.99,
            'WarehouseCode' => '01',
        ],
        [
            'ItemCode' => 'A002',
            'Quantity' => 5,
            'UnitPrice' => 149.99,
            'WarehouseCode' => '01',
        ],
    ],
]);

echo "Created order with DocEntry: " . $order['DocEntry'] . PHP_EOL;

// =============================================================================
// UPDATING RECORDS
// =============================================================================

// Update a business partner
SapB1::update('BusinessPartners', 'C001', [
    'Phone1' => '+1-555-999-8888',
    'EmailAddress' => 'updated@example.com',
]);

// Update an item
SapB1::update('Items', 'A001', [
    'ItemName' => 'Widget Pro - Updated',
    'SalesUnitPrice' => 109.99,
]);

// =============================================================================
// DELETING RECORDS
// =============================================================================

// Delete a business partner (use with caution!)
// SapB1::delete('BusinessPartners', 'C002');

// Cancel a document (orders can't be deleted, only cancelled)
SapB1::action('Orders', $order['DocEntry'], 'Cancel');

// =============================================================================
// USING DIFFERENT CONNECTIONS
// =============================================================================

// Work with multiple SAP B1 servers
$productionItems = SapB1::connection('production')->get('Items')->value();
$stagingItems = SapB1::connection('staging')->get('Items')->value();

// Using the helper function
$items = sap_b1('production')->get('Items')->value();

// =============================================================================
// RESPONSE HANDLING
// =============================================================================

$response = SapB1::get('BusinessPartners');

// Check if request was successful
if ($response->successful()) {
    $partners = $response->value();
    $count = $response->count();

    echo "Found {$count} partners" . PHP_EOL;

    foreach ($partners as $partner) {
        echo "- {$partner['CardCode']}: {$partner['CardName']}" . PHP_EOL;
    }
}

// Access response metadata
$metadata = $response->metadata();
$nextLink = $response->nextLink();

// Get raw response data
$rawData = $response->json();
$statusCode = $response->status();
