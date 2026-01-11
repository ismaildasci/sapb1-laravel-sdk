# Error Handling

The SDK provides comprehensive error handling with detailed exceptions and human-readable error messages.

## Exception Types

| Exception | Description |
|-----------|-------------|
| `SapB1Exception` | Base exception for all SDK errors |
| `AuthenticationException` | Login/authentication failures |
| `ConnectionException` | Network/connectivity issues |
| `SessionExpiredException` | Session timeout or invalidation |
| `ServiceLayerException` | SAP B1 Service Layer errors |
| `BatchException` | Batch operation failures |
| `AttachmentException` | File upload/download errors |
| `JsonDecodeException` | Response parsing errors |
| `RateLimitException` | Rate limiting errors (429) |
| `CircuitBreakerOpenException` | Circuit breaker is open |
| `PoolExhaustedException` | No available sessions in pool |

## Basic Error Handling

```php
use SapB1\Facades\SapB1;
use SapB1\Exceptions\ServiceLayerException;
use SapB1\Exceptions\AuthenticationException;
use SapB1\Exceptions\ConnectionException;
use SapB1\Exceptions\SessionExpiredException;

try {
    $response = SapB1::create('Orders', $orderData);
} catch (AuthenticationException $e) {
    // Invalid credentials or login failure
    Log::error('SAP B1 authentication failed', [
        'message' => $e->getMessage(),
    ]);
} catch (SessionExpiredException $e) {
    // Session expired, auto-refresh failed
    Log::warning('SAP B1 session expired', [
        'connection' => $e->context['connection'] ?? 'unknown',
    ]);
} catch (ConnectionException $e) {
    // Network issues
    Log::error('SAP B1 connection failed', [
        'message' => $e->getMessage(),
    ]);
} catch (ServiceLayerException $e) {
    // SAP B1 business logic error
    Log::error('SAP B1 error', [
        'message' => $e->getMessage(),
        'sap_code' => $e->getSapCode(),
        'status' => $e->getStatusCode(),
    ]);
}
```

## ServiceLayerException Details

The `ServiceLayerException` provides rich error information:

```php
try {
    $response = SapB1::create('Orders', $invalidData);
} catch (ServiceLayerException $e) {
    // Basic info
    $message = $e->getMessage();        // Original SAP error
    $statusCode = $e->getStatusCode();  // HTTP status (400, 404, 500, etc.)
    $sapCode = $e->getSapCode();        // SAP error code ("-5002")

    // Human-readable info
    $humanMessage = $e->getHumanMessage();  // "Document total must not be negative"
    $suggestion = $e->getSuggestion();       // "Check document lines and prices"
    $category = $e->getCategory();           // "document_error"

    // Utility methods
    $isAuth = $e->isAuthError();      // Is this an auth error?
    $isRetryable = $e->isRetryable(); // Can we retry this?

    // Documentation link
    $docLink = $e->getDocLink();      // Link to SAP help

    // Full details
    $details = $e->getDetails();
    // [
    //     'message' => '...',
    //     'human_message' => '...',
    //     'suggestion' => '...',
    //     'category' => '...',
    //     'sap_code' => '-5002',
    //     'status_code' => 400,
    //     'is_retryable' => false,
    //     'doc_link' => 'https://...'
    // ]
}
```

## Error Categories

The SDK categorizes errors for easier handling:

| Category | Description |
|----------|-------------|
| `authentication` | Login/credential issues |
| `session` | Session-related errors |
| `document` | Document validation errors |
| `business_partner` | Customer/vendor errors |
| `item` | Product/inventory errors |
| `warehouse` | Warehouse/location errors |
| `financial` | Accounting/financial errors |
| `approval` | Approval process errors |
| `system` | System/server errors |
| `odata` | OData query errors |

```php
try {
    $response = SapB1::create('Orders', $data);
} catch (ServiceLayerException $e) {
    match ($e->getCategory()) {
        'document' => $this->handleDocumentError($e),
        'business_partner' => $this->handlePartnerError($e),
        'authentication' => $this->handleAuthError($e),
        default => $this->handleGenericError($e),
    };
}
```

## Common SAP B1 Error Codes

### Authentication Errors (-100 to -104)

```php
if ($e->isAuthError()) {
    // -100: Invalid login
    // -101: Session expired
    // -102: License issue
    // -103: User locked
    // -104: Password expired
}
```

### Document Errors (-5001 to -5006)

| Code | Description |
|------|-------------|
| -5001 | Document validation failed |
| -5002 | Negative total not allowed |
| -5003 | Missing required field |
| -5004 | Invalid document status |
| -5005 | Duplicate document number |
| -5006 | Cannot modify closed document |

### Business Partner Errors (-1001 to -1005)

| Code | Description |
|------|-------------|
| -1001 | Partner code exists |
| -1002 | Partner not found |
| -1003 | Invalid partner type |
| -1004 | Credit limit exceeded |
| -1005 | Partner is frozen |

## Response-Based Error Checking

```php
$response = SapB1::create('Orders', $data);

if ($response->failed()) {
    $errorMessage = $response->errorMessage();
    $errorCode = $response->errorCode();

    if ($response->clientError()) {
        // 4xx error - bad request
    } elseif ($response->serverError()) {
        // 5xx error - server issue
    }
}
```

## Retry Logic

Check if an error is retryable:

```php
try {
    $response = SapB1::create('Orders', $data);
} catch (ServiceLayerException $e) {
    if ($e->isRetryable()) {
        // Safe to retry (e.g., timeout, temporary error)
        sleep(2);
        $response = SapB1::create('Orders', $data);
    } else {
        // Don't retry (e.g., validation error)
        throw $e;
    }
}
```

## Global Exception Handler

Handle SAP B1 exceptions in `app/Exceptions/Handler.php`:

```php
use SapB1\Exceptions\SapB1Exception;
use SapB1\Exceptions\ServiceLayerException;
use SapB1\Exceptions\AuthenticationException;

public function register(): void
{
    $this->renderable(function (ServiceLayerException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => $e->getHumanMessage(),
                'code' => $e->getSapCode(),
                'suggestion' => $e->getSuggestion(),
            ], $e->getStatusCode() ?: 500);
        }

        return back()->withErrors([
            'sap_error' => $e->getHumanMessage(),
        ]);
    });

    $this->renderable(function (AuthenticationException $e, $request) {
        Log::critical('SAP B1 authentication failed', [
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'error' => 'SAP B1 connection unavailable',
        ], 503);
    });
}
```

## Form Request Validation

Create a custom form request for SAP operations:

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'CardCode' => 'required|string',
            'DocDueDate' => 'required|date',
            'DocumentLines' => 'required|array|min:1',
            'DocumentLines.*.ItemCode' => 'required|string',
            'DocumentLines.*.Quantity' => 'required|numeric|min:0.01',
        ];
    }
}
```

## Logging Best Practices

```php
use SapB1\Exceptions\ServiceLayerException;

try {
    $response = SapB1::create('Orders', $data);
} catch (ServiceLayerException $e) {
    Log::error('SAP B1 order creation failed', [
        'error' => $e->getMessage(),
        'human_message' => $e->getHumanMessage(),
        'sap_code' => $e->getSapCode(),
        'category' => $e->getCategory(),
        'suggestion' => $e->getSuggestion(),
        'is_retryable' => $e->isRetryable(),
        'order_data' => $data,
        'context' => $e->context,
    ]);

    throw $e;
}
```

## User-Friendly Error Messages

```php
class OrderController extends Controller
{
    public function store(CreateOrderRequest $request)
    {
        try {
            $response = SapB1::create('Orders', $request->validated());

            return redirect()->route('orders.show', $response->entity()['DocEntry'])
                ->with('success', 'Order created successfully');

        } catch (ServiceLayerException $e) {
            return back()
                ->withInput()
                ->withErrors([
                    'order' => $this->translateError($e),
                ]);
        }
    }

    private function translateError(ServiceLayerException $e): string
    {
        // Use human message if available
        $message = $e->getHumanMessage();

        // Add suggestion if available
        if ($suggestion = $e->getSuggestion()) {
            $message .= " {$suggestion}";
        }

        return $message;
    }
}
```

## Monitoring Errors

Use events to monitor errors:

```php
use SapB1\Events\RequestFailed;

Event::listen(RequestFailed::class, function ($event) {
    // Send to error tracking service
    Sentry::captureException($event->exception, [
        'extra' => [
            'connection' => $event->connection,
            'endpoint' => $event->endpoint,
            'method' => $event->method,
        ],
    ]);
});
```

## Next Steps

- [Events](events.md) - Listen to error events
- [Health Checks](health-checks.md) - Monitor connection health
- [Circuit Breaker](circuit-breaker.md) - Prevent cascading failures
