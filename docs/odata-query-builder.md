# OData Query Builder

The OData Query Builder provides a fluent interface for constructing SAP B1 Service Layer queries.

## Basic Usage

```php
use SapB1\Facades\SapB1;
use SapB1\Client\ODataBuilder;

// Via client
$response = SapB1::query()
    ->select('CardCode', 'CardName')
    ->where('CardType', 'cCustomer')
    ->top(10)
    ->get('BusinessPartners');

// Standalone builder
$query = ODataBuilder::make()
    ->select('ItemCode', 'ItemName', 'QuantityOnStock')
    ->where('QuantityOnStock', 'gt', 0)
    ->orderByDesc('QuantityOnStock');

$response = SapB1::withOData($query)->get('Items');
```

## Select Fields

```php
// Multiple arguments
->select('CardCode', 'CardName', 'Balance')

// Array syntax
->select(['CardCode', 'CardName', 'Balance'])

// Chainable
->select('CardCode')
->select('CardName')
```

## Filtering

### Basic Comparison

```php
// Equals (default)
->where('CardType', 'cCustomer')

// With operator
->where('Balance', 'gt', 1000)
->where('Balance', 'ge', 1000)  // greater or equal
->where('Balance', 'lt', 1000)  // less than
->where('Balance', 'le', 1000)  // less or equal
->where('CardType', 'ne', 'cLead')  // not equal
```

### Convenience Methods

```php
// Not equal
->whereNot('CardType', 'cLead')

// Greater than
->whereGreaterThan('Balance', 1000)
->whereGreaterThanOrEqual('Balance', 1000)

// Less than
->whereLessThan('Balance', 1000)
->whereLessThanOrEqual('Balance', 1000)

// Null checks
->whereNull('Phone1')
->whereNotNull('EmailAddress')

// Between
->whereBetween('Balance', 1000, 5000)

// In array
->whereIn('CardCode', ['C001', 'C002', 'C003'])
```

### String Functions

```php
// Contains (LIKE %value%)
->whereContains('CardName', 'Corp')

// Starts with
->whereStartsWith('CardCode', 'C')

// Ends with
->whereEndsWith('CardName', 'Ltd')
```

### OR Conditions

```php
->where('CardType', 'cCustomer')
->orWhere('CardType', 'cLead')
// Result: (CardType eq 'cCustomer' or CardType eq 'cLead')
```

### Raw Filters

For complex conditions:

```php
->filter("year(CreateDate) eq 2025")
->filter("contains(CardName, 'Tech') and Balance gt 0")
```

## Ordering

```php
// Ascending (default)
->orderBy('CardName')

// Explicit direction
->orderBy('CardName', 'asc')
->orderBy('Balance', 'desc')

// Descending shorthand
->orderByDesc('Balance')

// Multiple fields
->orderBy('CardType')
->orderByDesc('Balance')
```

## Pagination

### Top and Skip

```php
// Limit results
->top(10)
->limit(10)  // alias for top

// Skip results
->skip(20)
->offset(20)  // alias for skip
```

### Page Helper

```php
// Page 1, 20 items per page
->page(1, 20)

// Page 3, 50 items per page
->page(3, 50)
```

## Expand Related Entities

```php
// Single expansion
->expand('BPAddresses')

// Multiple expansions
->expand('BPAddresses', 'ContactEmployees')

// Array syntax
->expand(['BPAddresses', 'ContactEmployees', 'BPBankAccounts'])
```

## Inline Count

Get total count alongside results:

```php
$response = SapB1::query()
    ->where('CardType', 'cCustomer')
    ->withCount()  // or ->inlineCount()
    ->top(20)
    ->get('BusinessPartners');

$customers = $response->value();
$totalCount = $response->count();

echo "Showing " . count($customers) . " of {$totalCount} customers";
```

## Cross-Company Query

Query across multiple companies (if configured):

```php
// All companies
->crossCompany()

// Specific company
->crossCompany('COMPANY_B')
```

## Strict Mode

The builder validates field names and operators by default:

```php
// Disable strict mode (use with caution)
->withoutStrictMode()
->filter("SomeComplexExpression eq true")

// Re-enable
->strictMode()
```

## Building the Query String

```php
$query = ODataBuilder::make()
    ->select('CardCode', 'CardName')
    ->where('CardType', 'cCustomer')
    ->top(10);

// Get query string
$queryString = $query->build();
// ?$select=CardCode,CardName&$filter=CardType eq 'cCustomer'&$top=10

// Get as array
$params = $query->toArray();
// ['$select' => 'CardCode,CardName', '$filter' => '...', '$top' => '10']
```

## Cloning and Reset

```php
$baseQuery = ODataBuilder::make()
    ->where('CardType', 'cCustomer')
    ->orderBy('CardName');

// Clone for modification
$topCustomers = $baseQuery->clone()
    ->where('Balance', 'gt', 10000)
    ->top(10);

// Reset all parameters
$baseQuery->reset();
```

## Complete Example

```php
use SapB1\Facades\SapB1;

// Find high-value customers with contact info
$response = SapB1::query()
    ->select('CardCode', 'CardName', 'Balance', 'Phone1', 'EmailAddress')
    ->expand('ContactEmployees')
    ->where('CardType', 'cCustomer')
    ->where('Balance', 'gt', 50000)
    ->whereNotNull('EmailAddress')
    ->whereContains('CardName', 'Corp')
    ->orderByDesc('Balance')
    ->withCount()
    ->page(1, 20)
    ->get('BusinessPartners');

if ($response->successful()) {
    echo "Found {$response->count()} matching customers\n";

    foreach ($response->value() as $customer) {
        echo "{$customer['CardCode']}: {$customer['CardName']} - {$customer['Balance']}\n";
    }
}
```

## Supported OData Operators

| Operator | Description | Example |
|----------|-------------|---------|
| eq | Equal | `where('Field', 'eq', 'value')` |
| ne | Not equal | `where('Field', 'ne', 'value')` |
| gt | Greater than | `where('Field', 'gt', 100)` |
| ge | Greater or equal | `where('Field', 'ge', 100)` |
| lt | Less than | `where('Field', 'lt', 100)` |
| le | Less or equal | `where('Field', 'le', 100)` |

## Next Steps

- [Working with Responses](responses.md) - Handle query results
- [Making Requests](requests.md) - All request methods
- [Batch Operations](batch-operations.md) - Multiple operations
