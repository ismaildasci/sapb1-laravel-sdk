# Testing

The SDK provides utilities for mocking SAP B1 requests in your tests.

## Setup

```php
use SapB1\Testing\SapB1Fake;

class OrderTest extends TestCase
{
    use SapB1Fake;
}
```

## Fake Responses

```php
use SapB1\Testing\FakeResponse;

public function test_creates_order(): void
{
    $this->fakeSapB1([
        'Orders' => FakeResponse::entity([
            'DocEntry' => 123,
            'DocNum' => 'SO-001',
        ]),
    ]);

    // Your code that creates an order...

    $this->assertSapB1Post('Orders');
}
```

## Using Factories

```php
use SapB1\Testing\Factories\BusinessPartnerFactory;
use SapB1\Testing\Factories\ItemFactory;
use SapB1\Testing\Factories\OrderFactory;

$this->fakeSapB1([
    'BusinessPartners' => FakeResponse::collection([
        BusinessPartnerFactory::new()->make(),
        BusinessPartnerFactory::new()->supplier()->make(),
    ]),
    'Items' => FakeResponse::collection([
        ItemFactory::new()->withPrice(99.99)->make(),
    ]),
]);
```

## Factory Methods

### BusinessPartnerFactory

```php
BusinessPartnerFactory::new()->make();
BusinessPartnerFactory::new()->supplier()->make();
BusinessPartnerFactory::new()->frozen()->withAddress()->make();
```

### ItemFactory

```php
ItemFactory::new()->make();
ItemFactory::new()->service()->make();
ItemFactory::new()->withPrice(99.99)->withQuantity(100)->make();
```

### OrderFactory

```php
OrderFactory::new()->make();
OrderFactory::new()->withRandomLines(5)->make();
OrderFactory::new()->forCustomer('C001')->closed()->make();
```

## Assertions

```php
// Assert request was made
$this->assertSapB1Get('BusinessPartners');
$this->assertSapB1Post('Orders', ['CardCode' => 'C001']);
$this->assertSapB1Patch('Items', 'A001');
$this->assertSapB1Delete('Orders', 123);

// Assert no requests
$this->assertSapB1NothingSent();
```

## Error Responses

```php
$this->fakeSapB1([
    'Orders' => FakeResponse::error(400, 'Invalid CardCode'),
]);
```

Next: [Available Factories](testing-factories.md)
