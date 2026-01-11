<?php

declare(strict_types=1);

/**
 * OData Query Builder Examples with SAP Business One Laravel SDK
 *
 * The query builder provides a fluent interface for constructing complex
 * OData queries with filtering, sorting, pagination, and expansion.
 */

use SapB1\Facades\SapB1;

// =============================================================================
// SELECT - Choose specific fields
// =============================================================================

// Select specific fields to reduce payload size
$partners = SapB1::query()
    ->select('CardCode', 'CardName', 'Phone1', 'EmailAddress')
    ->get('BusinessPartners')
    ->value();

// Select with nested properties (for expanded entities)
$orders = SapB1::query()
    ->select('DocEntry', 'DocNum', 'CardCode', 'DocTotal', 'DocumentLines/ItemCode')
    ->expand('DocumentLines')
    ->get('Orders')
    ->value();

// =============================================================================
// FILTERING - Where clauses
// =============================================================================

// Simple equality
$customers = SapB1::query()
    ->where('CardType', 'cCustomer')
    ->get('BusinessPartners')
    ->value();

// With operator
$recentOrders = SapB1::query()
    ->where('DocDate', 'ge', '2024-01-01')
    ->get('Orders')
    ->value();

// Multiple conditions (AND)
$activeCustomers = SapB1::query()
    ->where('CardType', 'cCustomer')
    ->where('Valid', 'tYES')
    ->where('Frozen', 'tNO')
    ->get('BusinessPartners')
    ->value();

// OR conditions
$partnersOrSuppliers = SapB1::query()
    ->where('CardType', 'cCustomer')
    ->orWhere('CardType', 'cSupplier')
    ->get('BusinessPartners')
    ->value();

// =============================================================================
// STRING FILTERING
// =============================================================================

// Contains (like '%value%')
$corpPartners = SapB1::query()
    ->whereContains('CardName', 'Corp')
    ->get('BusinessPartners')
    ->value();

// Starts with (like 'value%')
$partnersA = SapB1::query()
    ->whereStartsWith('CardCode', 'C')
    ->get('BusinessPartners')
    ->value();

// Ends with (like '%value')
$gmailPartners = SapB1::query()
    ->whereEndsWith('EmailAddress', '@gmail.com')
    ->get('BusinessPartners')
    ->value();

// =============================================================================
// COMPARISON OPERATORS
// =============================================================================

// Greater than
$largeOrders = SapB1::query()
    ->where('DocTotal', 'gt', 10000)
    ->get('Orders')
    ->value();

// Less than or equal
$lowStock = SapB1::query()
    ->where('OnHand', 'le', 10)
    ->get('Items')
    ->value();

// Not equal
$nonPendingOrders = SapB1::query()
    ->where('DocStatus', 'ne', 'bost_Open')
    ->get('Orders')
    ->value();

// =============================================================================
// IN / BETWEEN OPERATORS
// =============================================================================

// Where in array
$specificPartners = SapB1::query()
    ->whereIn('CardCode', ['C001', 'C002', 'C003'])
    ->get('BusinessPartners')
    ->value();

// Between dates
$q1Orders = SapB1::query()
    ->whereBetween('DocDate', '2024-01-01', '2024-03-31')
    ->get('Orders')
    ->value();

// =============================================================================
// NULL CHECKS
// =============================================================================

// Where null
$noEmailPartners = SapB1::query()
    ->whereNull('EmailAddress')
    ->get('BusinessPartners')
    ->value();

// Where not null
$hasPhonePartners = SapB1::query()
    ->whereNotNull('Phone1')
    ->get('BusinessPartners')
    ->value();

// =============================================================================
// SORTING
// =============================================================================

// Single column ascending
$partnersByName = SapB1::query()
    ->orderBy('CardName')
    ->get('BusinessPartners')
    ->value();

// Single column descending
$recentOrdersFirst = SapB1::query()
    ->orderByDesc('DocDate')
    ->get('Orders')
    ->value();

// Multiple columns
$sortedOrders = SapB1::query()
    ->orderByDesc('DocDate')
    ->orderBy('DocNum')
    ->get('Orders')
    ->value();

// =============================================================================
// PAGINATION
// =============================================================================

// Top N records
$top10Orders = SapB1::query()
    ->top(10)
    ->orderByDesc('DocDate')
    ->get('Orders')
    ->value();

// Skip and take (offset pagination)
$page2 = SapB1::query()
    ->skip(20)
    ->top(20)
    ->get('BusinessPartners')
    ->value();

// Page-based pagination (helper method)
$page3 = SapB1::query()
    ->page(3, perPage: 20) // Page 3, 20 items per page
    ->get('BusinessPartners')
    ->value();

// Get inline count for total records
$response = SapB1::query()
    ->inlineCount()
    ->top(20)
    ->get('BusinessPartners');

$partners = $response->value();
$totalCount = $response->count(); // Total records matching filter

// =============================================================================
// EXPAND - Related entities
// =============================================================================

// Single expansion
$ordersWithLines = SapB1::query()
    ->expand('DocumentLines')
    ->get('Orders')
    ->value();

// Multiple expansions
$fullOrderData = SapB1::query()
    ->expand('DocumentLines', 'TaxExtension', 'AddressExtension')
    ->get('Orders')
    ->value();

// Nested expansion
$partnersWithAddresses = SapB1::query()
    ->expand('BPAddresses', 'ContactEmployees')
    ->get('BusinessPartners')
    ->value();

// =============================================================================
// COMBINING EVERYTHING
// =============================================================================

// Complex query example
$orders = SapB1::query()
    ->select('DocEntry', 'DocNum', 'CardCode', 'CardName', 'DocDate', 'DocTotal', 'DocStatus')
    ->where('DocDate', 'ge', '2024-01-01')
    ->where('DocStatus', 'bost_Open')
    ->where('DocTotal', 'gt', 1000)
    ->whereContains('CardName', 'Corp')
    ->expand('DocumentLines')
    ->orderByDesc('DocDate')
    ->top(50)
    ->inlineCount()
    ->get('Orders');

echo "Found {$orders->count()} orders" . PHP_EOL;

foreach ($orders->value() as $order) {
    echo "{$order['DocNum']}: {$order['CardName']} - \${$order['DocTotal']}" . PHP_EOL;
}

// =============================================================================
// RAW FILTER (For complex OData expressions)
// =============================================================================

// When you need full control over the filter expression
$complexFilter = SapB1::query()
    ->whereRaw("(CardType eq 'cCustomer' and Balance gt 0) or (CardType eq 'cSupplier' and Balance lt 0)")
    ->get('BusinessPartners')
    ->value();

// =============================================================================
// CROSS-COMPANY QUERIES
// =============================================================================

// Query across all company databases
$allCompanyItems = SapB1::query()
    ->crossCompany('*')
    ->get('Items')
    ->value();

// Query specific company
$otherCompanyItems = SapB1::query()
    ->crossCompany('SBODEMOUS')
    ->get('Items')
    ->value();

// =============================================================================
// SQL QUERIES (For stored queries)
// =============================================================================

// Execute a stored SQL query
$result = SapB1::sql('GetCustomerBalance')
    ->param('CardCode', 'C001')
    ->execute();

// With pagination
$result = SapB1::sql('GetTopCustomers')
    ->top(10)
    ->skip(0)
    ->execute();

// =============================================================================
// SEMANTIC LAYER QUERIES
// =============================================================================

// Query semantic layer views (for BI/reporting)
$salesAnalysis = SapB1::semantic('SalesAnalysis')
    ->dimensions('ItemCode', 'CardCode')
    ->measures('Quantity', 'LineTotal')
    ->filter('DocDate', 'ge', '2024-01-01')
    ->execute();

// =============================================================================
// QUERY CACHING
// =============================================================================

// Enable caching for expensive queries
$items = SapB1::query()
    ->cache(ttl: 3600) // Cache for 1 hour
    ->get('Items')
    ->value();

// Skip cache for this request
$freshData = SapB1::query()
    ->withoutCache()
    ->get('Items')
    ->value();

// =============================================================================
// ODATA V4 QUERIES
// =============================================================================

// Use OData v4 (SAP FP 2405+)
$items = SapB1::useODataV4()
    ->query()
    ->where('ItemType', 'itItems')
    ->get('Items')
    ->value();
