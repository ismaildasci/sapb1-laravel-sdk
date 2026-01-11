# Working with Responses

The `Response` class provides a rich API for working with SAP B1 Service Layer responses.

## Basic Response Handling

```php
use SapB1\Facades\SapB1;

$response = SapB1::get('BusinessPartners');

// Check status
if ($response->successful()) {
    $partners = $response->value();
}
```

## Status Methods

```php
// HTTP status code
$status = $response->status(); // 200, 400, 500, etc.

// Status checks
$response->successful();  // 2xx
$response->redirect();    // 3xx
$response->clientError(); // 4xx
$response->serverError(); // 5xx
$response->failed();      // 4xx or 5xx
```

## Accessing Data

### Collection Results

For endpoints that return multiple records:

```php
$response = SapB1::get('BusinessPartners');

// Get the value array
$partners = $response->value();
// Returns: [['CardCode' => 'C001', ...], ['CardCode' => 'C002', ...]]

foreach ($partners as $partner) {
    echo $partner['CardCode'] . ': ' . $partner['CardName'];
}
```

### Single Entity

For find operations or single record returns:

```php
$response = SapB1::find('BusinessPartners', 'C001');

// Get single entity
$partner = $response->entity();
// Returns: ['CardCode' => 'C001', 'CardName' => 'Customer One', ...]

echo $partner['CardName'];
```

### JSON Access

Access nested data using dot notation:

```php
$response = SapB1::find('Orders', 123);

// Direct access
$cardCode = $response->json('CardCode');

// Nested access
$firstLine = $response->json('DocumentLines.0');
$itemCode = $response->json('DocumentLines.0.ItemCode');

// With default value
$discount = $response->json('DiscountPercent', 0);

// Get all JSON data
$allData = $response->json();
```

### Dynamic Property Access

```php
$response = SapB1::find('BusinessPartners', 'C001');

// Access as property
echo $response->CardCode;
echo $response->CardName;

// Check existence
if (isset($response->Phone1)) {
    echo $response->Phone1;
}
```

## Pagination

### Check for More Pages

```php
$response = SapB1::get('BusinessPartners');

if ($response->hasNextPage()) {
    $nextResponse = SapB1::nextPage($response);
}
```

### Get Next Link

```php
$nextLink = $response->nextLink();
// Returns: "https://server/b1s/v1/BusinessPartners?$skip=20"

$skipToken = $response->skipToken();
// Returns: "20" (the skip value)
```

### Iterate All Pages

```php
// Manual iteration
$response = SapB1::get('BusinessPartners');

while ($response !== null) {
    foreach ($response->value() as $partner) {
        processPartner($partner);
    }

    $response = $response->hasNextPage()
        ? SapB1::nextPage($response)
        : null;
}

// Using generator
foreach (SapB1::paginate('BusinessPartners') as $response) {
    foreach ($response->value() as $partner) {
        processPartner($partner);
    }
}
```

## Count and Metadata

### Inline Count

```php
$response = SapB1::query()
    ->withCount()
    ->top(20)
    ->get('BusinessPartners');

$totalRecords = $response->count();
$currentPage = $response->value();

echo "Showing " . count($currentPage) . " of {$totalRecords}";
```

### OData Context

```php
$context = $response->context();
// Returns: "$metadata#BusinessPartners"
```

## Error Information

### Check for Errors

```php
if ($response->hasError()) {
    $message = $response->errorMessage();
    $code = $response->errorCode();

    Log::error("SAP B1 Error: {$message} (Code: {$code})");
}
```

### Error Response Structure

```php
$response = SapB1::create('Orders', $invalidData);

if ($response->failed()) {
    echo $response->errorMessage();
    // "Document total must not be negative"

    echo $response->errorCode();
    // "-5002"
}
```

## Headers

```php
// Get specific header
$contentType = $response->header('Content-Type');

// Get request ID (if enabled)
$requestId = $response->getRequestId();

// Get all headers
$headers = $response->headers();
```

## Conversion Methods

### To Array

```php
$array = $response->toArray();
// Returns all decoded JSON data as array
```

### To JSON

```php
$json = $response->toJson();
// Returns JSON string

// With options
$json = $response->toJson(JSON_PRETTY_PRINT);
```

### Get Raw Body

```php
$rawBody = $response->body();
// Returns raw response string
```

### Get PSR-7 Response

```php
use Psr\Http\Message\ResponseInterface;

$psrResponse = $response->toPsrResponse();
```

## Helper Methods

### Check if Value Exists

```php
if ($response->hasValue()) {
    // Response contains a "value" array (collection)
    $items = $response->value();
} else {
    // Single entity response
    $entity = $response->entity();
}
```

## Complete Example

```php
use SapB1\Facades\SapB1;

$response = SapB1::query()
    ->select('CardCode', 'CardName', 'Balance')
    ->where('CardType', 'cCustomer')
    ->where('Balance', 'gt', 0)
    ->withCount()
    ->orderByDesc('Balance')
    ->top(10)
    ->get('BusinessPartners');

if ($response->failed()) {
    throw new Exception("Query failed: " . $response->errorMessage());
}

$totalCustomers = $response->count();
$customers = $response->value();

echo "Top 10 customers by balance (of {$totalCustomers} total):\n";

foreach ($customers as $customer) {
    printf(
        "%s: %s - Balance: %.2f\n",
        $customer['CardCode'],
        $customer['CardName'],
        $customer['Balance']
    );
}

// Process all pages
while ($response->hasNextPage()) {
    $response = SapB1::nextPage($response);

    foreach ($response->value() as $customer) {
        // Process remaining customers
    }
}
```

## JSON Serialization

The Response class implements `JsonSerializable`:

```php
// In a controller
return response()->json($response);

// Manual serialization
$json = json_encode($response);
```

## Next Steps

- [Error Handling](error-handling.md) - Handle errors gracefully
- [OData Query Builder](odata-query-builder.md) - Build queries
- [Making Requests](requests.md) - All request methods
