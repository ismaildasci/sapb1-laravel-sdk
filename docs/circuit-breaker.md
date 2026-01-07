# Circuit Breaker

The circuit breaker pattern prevents cascading failures by temporarily blocking requests when the SAP B1 server is experiencing issues.

## How It Works

```
CLOSED → (failures exceed threshold) → OPEN
                                         ↓
                                    (wait duration)
                                         ↓
CLOSED ← (success in half-open) ← HALF_OPEN
```

- **Closed**: Normal operation, requests flow through
- **Open**: Requests blocked immediately, fail fast
- **Half-Open**: Limited requests allowed to test recovery

## Enable Circuit Breaker

```env
SAP_B1_CIRCUIT_BREAKER=true
```

```php
// config/sap-b1.php
'http' => [
    'circuit_breaker' => [
        'enabled' => true,
        'failure_threshold' => 5,
        'open_duration' => 30,
        'half_open_max_attempts' => 3,
        'scope' => 'global', // or 'endpoint'
    ],
],
```

## Per-Request Control

```php
use SapB1\Facades\SapB1;

// Disable for specific request
$response = SapB1::withoutCircuitBreaker()->get('Items');

// Force enable
$response = SapB1::withCircuitBreaker()->get('Orders');
```

## What Counts as Failure

Only real errors increment the failure counter:
- Connection timeouts
- 5xx server errors

Successful responses (even slow ones) reset the counter.

## Handling Open Circuit

```php
use SapB1\Exceptions\CircuitBreakerOpenException;

try {
    $response = SapB1::get('BusinessPartners');
} catch (CircuitBreakerOpenException $e) {
    // Circuit is open, SAP B1 is likely down
    // Return cached data or graceful fallback
}
```

## Monitoring

```php
use SapB1\Events\CircuitBreakerStateChanged;

// Listen to state changes
Event::listen(CircuitBreakerStateChanged::class, function ($event) {
    Log::warning("Circuit breaker: {$event->previousState} → {$event->newState}");
});
```

Next: [Query Caching](caching.md)
