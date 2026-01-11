# Events

The SDK dispatches Laravel events throughout the request lifecycle, allowing you to hook into various operations.

## Available Events

### Session Events

| Event | Description |
|-------|-------------|
| `SessionCreated` | Fired when a new session is created |
| `SessionRefreshed` | Fired when a session is refreshed |
| `SessionExpired` | Fired when a session expires |
| `SessionAcquired` | Fired when a session is acquired from pool |
| `SessionReleased` | Fired when a session is released to pool |

### Request Events

| Event | Description |
|-------|-------------|
| `RequestSending` | Fired before a request is sent |
| `RequestSent` | Fired after a successful response |
| `RequestFailed` | Fired when a request fails |

### Pool Events

| Event | Description |
|-------|-------------|
| `PoolWarmedUp` | Fired when pool warmup completes |
| `PoolSessionExpired` | Fired when a pool session expires |

### Circuit Breaker Events

| Event | Description |
|-------|-------------|
| `CircuitBreakerStateChanged` | Fired when circuit breaker state changes |

## Listening to Events

### Event Subscriber

Create an event subscriber:

```php
namespace App\Listeners;

use SapB1\Events\SessionCreated;
use SapB1\Events\SessionExpired;
use SapB1\Events\RequestSending;
use SapB1\Events\RequestSent;
use SapB1\Events\RequestFailed;
use Illuminate\Events\Dispatcher;

class SapB1EventSubscriber
{
    public function handleSessionCreated(SessionCreated $event): void
    {
        Log::info('SAP B1 session created', [
            'connection' => $event->connection,
            'session_id' => $event->sessionId,
            'company_db' => $event->companyDb,
        ]);
    }

    public function handleSessionExpired(SessionExpired $event): void
    {
        Log::warning('SAP B1 session expired', [
            'connection' => $event->connection,
        ]);
    }

    public function handleRequestSending(RequestSending $event): void
    {
        Log::debug('SAP B1 request sending', [
            'connection' => $event->connection,
            'method' => $event->method,
            'endpoint' => $event->endpoint,
        ]);
    }

    public function handleRequestSent(RequestSent $event): void
    {
        Log::debug('SAP B1 request completed', [
            'connection' => $event->connection,
            'method' => $event->method,
            'endpoint' => $event->endpoint,
            'status' => $event->response->status(),
            'duration_ms' => $event->durationMs,
        ]);
    }

    public function handleRequestFailed(RequestFailed $event): void
    {
        Log::error('SAP B1 request failed', [
            'connection' => $event->connection,
            'method' => $event->method,
            'endpoint' => $event->endpoint,
            'error' => $event->exception->getMessage(),
        ]);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            SessionCreated::class => 'handleSessionCreated',
            SessionExpired::class => 'handleSessionExpired',
            RequestSending::class => 'handleRequestSending',
            RequestSent::class => 'handleRequestSent',
            RequestFailed::class => 'handleRequestFailed',
        ];
    }
}
```

### Register the Subscriber

In `App\Providers\EventServiceProvider`:

```php
protected $subscribe = [
    \App\Listeners\SapB1EventSubscriber::class,
];
```

### Individual Listeners

Alternatively, register individual listeners:

```php
// In EventServiceProvider
protected $listen = [
    \SapB1\Events\SessionCreated::class => [
        \App\Listeners\LogSessionCreation::class,
    ],
    \SapB1\Events\RequestFailed::class => [
        \App\Listeners\NotifyOnSapB1Error::class,
    ],
];
```

## Event Details

### SessionCreated

Fired when a new session is established:

```php
use SapB1\Events\SessionCreated;

class LogSessionCreation
{
    public function handle(SessionCreated $event): void
    {
        // Available properties
        $connection = $event->connection;  // "default"
        $sessionId = $event->sessionId;    // "abc123..."
        $companyDb = $event->companyDb;    // "SBODEMOUS"
    }
}
```

### SessionRefreshed

Fired when a session is refreshed:

```php
use SapB1\Events\SessionRefreshed;

class TrackSessionRefresh
{
    public function handle(SessionRefreshed $event): void
    {
        $connection = $event->connection;
        $sessionId = $event->sessionId;
        $companyDb = $event->companyDb;
    }
}
```

### SessionExpired

Fired when a session expires:

```php
use SapB1\Events\SessionExpired;

class AlertOnSessionExpiry
{
    public function handle(SessionExpired $event): void
    {
        $connection = $event->connection;

        // Maybe notify monitoring system
    }
}
```

### RequestSending

Fired before each request:

```php
use SapB1\Events\RequestSending;

class TraceOutgoingRequests
{
    public function handle(RequestSending $event): void
    {
        $connection = $event->connection;  // "default"
        $method = $event->method;          // "GET", "POST", etc.
        $endpoint = $event->endpoint;      // "BusinessPartners"
        $options = $event->options;        // Request options array
    }
}
```

### RequestSent

Fired after a successful response:

```php
use SapB1\Events\RequestSent;

class LogRequestMetrics
{
    public function handle(RequestSent $event): void
    {
        $connection = $event->connection;
        $method = $event->method;
        $endpoint = $event->endpoint;
        $response = $event->response;      // Response object
        $durationMs = $event->durationMs;  // Request duration

        // Log slow queries
        if ($durationMs > 1000) {
            Log::warning('Slow SAP B1 query', [
                'endpoint' => $endpoint,
                'duration_ms' => $durationMs,
            ]);
        }
    }
}
```

### RequestFailed

Fired when a request fails:

```php
use SapB1\Events\RequestFailed;
use SapB1\Exceptions\ServiceLayerException;

class NotifyOnSapB1Errors
{
    public function handle(RequestFailed $event): void
    {
        $connection = $event->connection;
        $method = $event->method;
        $endpoint = $event->endpoint;
        $exception = $event->exception;

        // Send notification for critical errors
        if ($exception instanceof ServiceLayerException) {
            if ($exception->isAuthError()) {
                // Handle auth errors differently
                Notification::route('slack', config('slack.webhook'))
                    ->notify(new SapB1AuthErrorNotification($event));
            }
        }
    }
}
```

### CircuitBreakerStateChanged

Fired when the circuit breaker changes state:

```php
use SapB1\Events\CircuitBreakerStateChanged;

class MonitorCircuitBreaker
{
    public function handle(CircuitBreakerStateChanged $event): void
    {
        $connection = $event->connection;
        $previousState = $event->previousState;  // "closed", "open", "half_open"
        $currentState = $event->currentState;

        if ($currentState === 'open') {
            Log::critical('SAP B1 circuit breaker opened', [
                'connection' => $connection,
            ]);
        }
    }
}
```

### Pool Events

```php
use SapB1\Events\SessionAcquired;
use SapB1\Events\SessionReleased;
use SapB1\Events\PoolWarmedUp;
use SapB1\Events\PoolSessionExpired;

// Session acquired from pool
Event::listen(SessionAcquired::class, function ($event) {
    $connection = $event->connection;
    $sessionId = $event->sessionId;
});

// Session released back to pool
Event::listen(SessionReleased::class, function ($event) {
    $connection = $event->connection;
    $sessionId = $event->sessionId;
    $wasInvalidated = $event->invalidated;
});

// Pool warmup completed
Event::listen(PoolWarmedUp::class, function ($event) {
    $connection = $event->connection;
    $sessionsCreated = $event->count;
});

// Pool session expired
Event::listen(PoolSessionExpired::class, function ($event) {
    $connection = $event->connection;
    $sessionId = $event->sessionId;
});
```

## Use Cases

### Request Logging

```php
use SapB1\Events\RequestSent;

class SapB1RequestLogger
{
    public function handle(RequestSent $event): void
    {
        DB::table('sap_b1_logs')->insert([
            'connection' => $event->connection,
            'method' => $event->method,
            'endpoint' => $event->endpoint,
            'status' => $event->response->status(),
            'duration_ms' => $event->durationMs,
            'created_at' => now(),
        ]);
    }
}
```

### Performance Monitoring

```php
use SapB1\Events\RequestSent;
use Illuminate\Support\Facades\Redis;

class SapB1MetricsCollector
{
    public function handle(RequestSent $event): void
    {
        $key = "sap_b1:metrics:{$event->connection}:{$event->endpoint}";

        Redis::pipeline(function ($pipe) use ($key, $event) {
            $pipe->incr("{$key}:count");
            $pipe->incrByFloat("{$key}:total_time", $event->durationMs);
        });
    }
}
```

### Error Alerting

```php
use SapB1\Events\RequestFailed;
use App\Notifications\SapB1ErrorNotification;

class SapB1ErrorAlerter
{
    public function handle(RequestFailed $event): void
    {
        $admin = User::where('role', 'admin')->first();

        $admin->notify(new SapB1ErrorNotification(
            connection: $event->connection,
            endpoint: $event->endpoint,
            error: $event->exception->getMessage()
        ));
    }
}
```

## Next Steps

- [Error Handling](error-handling.md) - Handle exceptions
- [Health Checks](health-checks.md) - Monitor connections
- [Circuit Breaker](circuit-breaker.md) - Prevent cascading failures
