# Testing Factories

The SDK provides test factories and fake clients to help you write tests without connecting to a real SAP B1 server.

## Available Factories

| Factory | Description |
|---------|-------------|
| `BusinessPartnerFactory` | Generate business partner data |
| `ItemFactory` | Generate product/item data |
| `OrderFactory` | Generate sales order data |

## Quick Start

### Using the Fake Client

```php
use SapB1\Testing\SapB1Fake;
use SapB1\Testing\FakeResponse;

class OrderTest extends TestCase
{
    use SapB1Fake;

    public function test_creates_order(): void
    {
        $this->fakeSapB1([
            'Orders' => FakeResponse::success([
                'DocEntry' => 123,
                'DocNum' => 1001,
                'CardCode' => 'C001',
            ]),
        ]);

        // Your code that creates an order
        $result = $this->orderService->create([
            'CardCode' => 'C001',
            'DocumentLines' => [
                ['ItemCode' => 'A001', 'Quantity' => 5],
            ],
        ]);

        $this->assertEquals(123, $result['DocEntry']);
    }
}
```

## SapB1Fake Trait

The `SapB1Fake` trait provides methods for mocking SAP B1 responses:

```php
use SapB1\Testing\SapB1Fake;
use SapB1\Testing\FakeResponse;

class MyTest extends TestCase
{
    use SapB1Fake;

    public function test_example(): void
    {
        // Configure fake responses
        $this->fakeSapB1([
            'BusinessPartners' => FakeResponse::collection([
                ['CardCode' => 'C001', 'CardName' => 'Customer One'],
                ['CardCode' => 'C002', 'CardName' => 'Customer Two'],
            ]),
        ]);

        // Your test code...
    }
}
```

## FakeResponse Methods

### Success Response

```php
// Single entity
FakeResponse::success([
    'DocEntry' => 123,
    'DocNum' => 1001,
]);

// With custom status code
FakeResponse::success($data, 201);
```

### Collection Response

```php
// Multiple records
FakeResponse::collection([
    ['CardCode' => 'C001', 'CardName' => 'Customer One'],
    ['CardCode' => 'C002', 'CardName' => 'Customer Two'],
]);

// With count
FakeResponse::collection($items, count: 100);
```

### Error Response

```php
// Simple error
FakeResponse::error('Business partner not found', 404);

// With SAP error code
FakeResponse::error('Document total must not be negative', 400, '-5002');

// SAP-style error
FakeResponse::sapError([
    'code' => '-5002',
    'message' => ['value' => 'Document total must not be negative'],
]);
```

### Empty Responses

```php
// Empty collection
FakeResponse::empty();

// No content (204)
FakeResponse::noContent();
```

### Paginated Response

```php
FakeResponse::paginated(
    items: $items,
    nextLink: 'BusinessPartners?$skip=20',
    count: 100
);
```

## Using Factories

### BusinessPartnerFactory

```php
use SapB1\Testing\Factories\BusinessPartnerFactory;

// Create default customer
$customer = BusinessPartnerFactory::new()->make();
// ['CardCode' => 'C...', 'CardName' => '...', 'CardType' => 'cCustomer', ...]

// Create supplier
$supplier = BusinessPartnerFactory::new()->supplier()->make();

// Create lead
$lead = BusinessPartnerFactory::new()->lead()->make();

// Frozen partner
$frozen = BusinessPartnerFactory::new()->frozen()->make();

// Inactive partner
$inactive = BusinessPartnerFactory::new()->inactive()->make();

// With address
$withAddress = BusinessPartnerFactory::new()
    ->withAddress([
        'Street' => 'Custom Street',
        'City' => 'Ankara',
    ])
    ->make();

// With contact
$withContact = BusinessPartnerFactory::new()
    ->withContact([
        'Name' => 'John Doe',
        'Phone1' => '+90 555 123 4567',
    ])
    ->make();

// Custom attributes
$custom = BusinessPartnerFactory::new()
    ->state([
        'CardCode' => 'C00001',
        'CardName' => 'Specific Customer',
        'Balance' => 50000,
    ])
    ->make();

// Create multiple
$customers = BusinessPartnerFactory::new()->count(10)->make();
```

### ItemFactory

```php
use SapB1\Testing\Factories\ItemFactory;

// Default item
$item = ItemFactory::new()->make();

// Service item
$service = ItemFactory::new()->service()->make();

// With stock
$withStock = ItemFactory::new()
    ->withStock(100, 'WH01')
    ->make();

// With price
$withPrice = ItemFactory::new()
    ->withPrice(99.99, 'USD')
    ->make();
```

### OrderFactory

```php
use SapB1\Testing\Factories\OrderFactory;

// Default order
$order = OrderFactory::new()->make();

// With specific customer
$order = OrderFactory::new()
    ->forCustomer('C001')
    ->make();

// With lines
$order = OrderFactory::new()
    ->withLines([
        ['ItemCode' => 'A001', 'Quantity' => 5, 'UnitPrice' => 100],
        ['ItemCode' => 'A002', 'Quantity' => 3, 'UnitPrice' => 250],
    ])
    ->make();

// Closed order
$closed = OrderFactory::new()->closed()->make();

// Cancelled order
$cancelled = OrderFactory::new()->cancelled()->make();
```

## Complete Test Example

```php
namespace Tests\Feature;

use Tests\TestCase;
use SapB1\Testing\SapB1Fake;
use SapB1\Testing\FakeResponse;
use SapB1\Testing\Factories\BusinessPartnerFactory;
use SapB1\Testing\Factories\OrderFactory;
use App\Services\OrderService;

class OrderServiceTest extends TestCase
{
    use SapB1Fake;

    private OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = app(OrderService::class);
    }

    public function test_creates_order_for_customer(): void
    {
        $customer = BusinessPartnerFactory::new()->make();
        $orderData = OrderFactory::new()
            ->forCustomer($customer['CardCode'])
            ->make();

        $this->fakeSapB1([
            "BusinessPartners('{$customer['CardCode']}')" => FakeResponse::success($customer),
            'Orders' => FakeResponse::success([
                'DocEntry' => 123,
                'DocNum' => 1001,
                ...$orderData,
            ]),
        ]);

        $result = $this->orderService->createOrder(
            $customer['CardCode'],
            $orderData['DocumentLines']
        );

        $this->assertNotNull($result);
        $this->assertEquals(123, $result['DocEntry']);
    }

    public function test_handles_customer_not_found(): void
    {
        $this->fakeSapB1([
            "BusinessPartners('INVALID')" => FakeResponse::error(
                'Business partner not found',
                404
            ),
        ]);

        $this->expectException(\App\Exceptions\CustomerNotFoundException::class);

        $this->orderService->createOrder('INVALID', []);
    }

    public function test_handles_credit_limit_exceeded(): void
    {
        $customer = BusinessPartnerFactory::new()->make();

        $this->fakeSapB1([
            "BusinessPartners('{$customer['CardCode']}')" => FakeResponse::success($customer),
            'Orders' => FakeResponse::sapError([
                'code' => '-1004',
                'message' => ['value' => 'Credit limit exceeded'],
            ], 400),
        ]);

        $this->expectException(\App\Exceptions\CreditLimitExceededException::class);

        $this->orderService->createOrder($customer['CardCode'], [
            ['ItemCode' => 'A001', 'Quantity' => 1000, 'UnitPrice' => 10000],
        ]);
    }

    public function test_lists_paginated_orders(): void
    {
        $orders = OrderFactory::new()->count(5)->make();

        $this->fakeSapB1([
            'Orders' => FakeResponse::paginated(
                items: $orders,
                nextLink: 'Orders?$skip=20',
                count: 100
            ),
        ]);

        $result = $this->orderService->listOrders();

        $this->assertCount(5, $result['items']);
        $this->assertEquals(100, $result['total']);
        $this->assertTrue($result['hasMore']);
    }
}
```

## Factory States

Chain multiple states:

```php
$partner = BusinessPartnerFactory::new()
    ->supplier()
    ->frozen()
    ->withAddress()
    ->withContact()
    ->state([
        'Currency' => 'USD',
        'Balance' => 10000,
    ])
    ->make();
```

## Custom Factories

Create your own factories by extending `SapB1Factory`:

```php
namespace Tests\Factories;

use SapB1\Testing\Factories\SapB1Factory;

class InvoiceFactory extends SapB1Factory
{
    public static function new(): static
    {
        return new self;
    }

    protected function definition(): array
    {
        return [
            'DocEntry' => $this->randomInt(1, 99999),
            'DocNum' => $this->randomInt(1000, 9999),
            'CardCode' => 'C' . $this->randomString(6),
            'DocDate' => now()->format('Y-m-d'),
            'DocDueDate' => now()->addDays(30)->format('Y-m-d'),
            'DocTotal' => $this->randomInt(100, 10000),
            'DocumentLines' => [
                [
                    'ItemCode' => 'A' . $this->randomString(5),
                    'Quantity' => $this->randomInt(1, 10),
                    'UnitPrice' => $this->randomInt(10, 100),
                ],
            ],
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'PaidToDate' => $this->attributes['DocTotal'] ?? 1000,
        ]);
    }

    public function overdue(): static
    {
        return $this->state([
            'DocDueDate' => now()->subDays(30)->format('Y-m-d'),
            'PaidToDate' => 0,
        ]);
    }
}
```

## Assertions

The fake client tracks requests for assertions:

```php
$this->fakeSapB1([...]);

// Your code...
$this->orderService->create($data);

// Assert requests were made
$this->assertSapB1RequestMade('POST', 'Orders');
$this->assertSapB1RequestCount(1);
$this->assertSapB1RequestPayload('Orders', ['CardCode' => 'C001']);
```

## Next Steps

- [Testing](testing.md) - General testing guide
- [Making Requests](requests.md) - Understand request flow
- [Error Handling](error-handling.md) - Test error scenarios
