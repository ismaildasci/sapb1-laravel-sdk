# Health Checks

The SDK provides comprehensive health checking capabilities to monitor SAP B1 Service Layer connectivity.

## Quick Check

### Via Artisan Command

```bash
# Check default connection
php artisan sap-b1:health

# Check specific connection
php artisan sap-b1:health --connection=production

# Check all connections
php artisan sap-b1:health --all

# JSON output (for monitoring tools)
php artisan sap-b1:health --json
```

### Sample Output

```
Checking SAP B1 connection health...

  SAP B1 connection is healthy

  Status ..................................... Healthy
  Connection ................................. default
  Company DB ................................. SBODEMOUS
  Response Time .............................. 245.32ms
  Session ID ................................. abc123...
```

## Programmatic Health Checks

### Using the Health Check Service

```php
use SapB1\Health\SapB1HealthCheck;

class HealthController
{
    public function check(SapB1HealthCheck $healthCheck)
    {
        $result = $healthCheck->check();

        return response()->json([
            'healthy' => $result->isHealthy(),
            'message' => $result->message,
            'response_time' => $result->responseTime,
            'company' => $result->companyDb,
        ]);
    }
}
```

### Check Specific Connection

```php
$result = $healthCheck->check('production');

if ($result->isHealthy()) {
    // Connection is working
} else {
    Log::error('SAP B1 unhealthy', ['message' => $result->message]);
}
```

### Check All Connections

```php
$results = $healthCheck->checkAll();

foreach ($results as $connection => $result) {
    echo "{$connection}: " . ($result->isHealthy() ? 'OK' : 'FAIL') . "\n";
}

// Check if all are healthy
if ($healthCheck->isHealthy()) {
    echo "All connections healthy!";
}
```

### Check Specific Connections

```php
$results = $healthCheck->checkAll(['production', 'warehouse']);
```

## Health Check Result

The `HealthCheckResult` class provides detailed information:

```php
$result = $healthCheck->check();

// Status
$result->isHealthy();    // true/false
$result->isUnhealthy();  // opposite of isHealthy()

// Details
$result->message;        // "SAP B1 connection is healthy"
$result->connection;     // "default"
$result->companyDb;      // "SBODEMOUS"
$result->sessionId;      // Current session ID
$result->responseTime;   // Response time in milliseconds

// Convert to array
$data = $result->toArray();
```

## Integration with Laravel Health

Integrate with popular health check packages:

### Spatie Laravel Health

```php
use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use SapB1\Health\SapB1HealthCheck;

class SapB1Check extends Check
{
    public function run(): Result
    {
        $healthCheck = app(SapB1HealthCheck::class);
        $result = $healthCheck->check();

        if ($result->isHealthy()) {
            return Result::make()
                ->ok("Connected to {$result->companyDb}")
                ->meta([
                    'response_time' => $result->responseTime,
                    'session_id' => $result->sessionId,
                ]);
        }

        return Result::make()
            ->failed($result->message);
    }
}

// Register in service provider
Health::checks([
    SapB1Check::new(),
]);
```

## Health Endpoint

Create a dedicated health endpoint:

```php
// routes/api.php
Route::get('/health/sap-b1', function (SapB1HealthCheck $healthCheck) {
    $results = $healthCheck->checkAll();
    $allHealthy = $healthCheck->isHealthy();

    return response()->json([
        'status' => $allHealthy ? 'healthy' : 'unhealthy',
        'timestamp' => now()->toIso8601String(),
        'connections' => collect($results)->map(fn($r) => [
            'healthy' => $r->isHealthy(),
            'message' => $r->message,
            'response_time_ms' => $r->responseTime,
            'company_db' => $r->companyDb,
        ]),
    ], $allHealthy ? 200 : 503);
});
```

## Monitoring and Alerting

### Scheduled Health Checks

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        $healthCheck = app(SapB1HealthCheck::class);

        foreach ($healthCheck->checkAll() as $connection => $result) {
            if ($result->isUnhealthy()) {
                Log::critical("SAP B1 connection '{$connection}' is unhealthy", [
                    'message' => $result->message,
                ]);

                // Send notification
                Notification::route('slack', config('services.slack.webhook'))
                    ->notify(new SapB1UnhealthyNotification($connection, $result));
            }
        }
    })->everyFiveMinutes();
}
```

### Health Check with Metrics

```php
use Illuminate\Support\Facades\Log;

class SapB1HealthMonitor
{
    public function __construct(
        protected SapB1HealthCheck $healthCheck
    ) {}

    public function check(): void
    {
        foreach ($this->healthCheck->checkAll() as $connection => $result) {
            // Log metrics for monitoring tools
            Log::info('sap_b1_health', [
                'connection' => $connection,
                'healthy' => $result->isHealthy(),
                'response_time_ms' => $result->responseTime ?? 0,
                'company_db' => $result->companyDb,
            ]);

            // Custom metrics (e.g., Prometheus, DataDog)
            if ($result->responseTime !== null) {
                // Record response time metric
                // Metrics::timing('sap_b1.response_time', $result->responseTime, ['connection' => $connection]);
            }
        }
    }
}
```

## Connection Diagnostics

For more detailed diagnostics:

```php
use SapB1\Facades\SapB1;

$diagnostics = SapB1::diagnostics();

// Run full diagnostics
$report = $diagnostics->run();

// Individual tests
$connectivity = $diagnostics->testConnectivity();
```

## Best Practices

### 1. Don't Block Application Start

```php
// In a service provider - defer health check
$this->app->booted(function () {
    if (config('sap-b1.health_check_on_boot', false)) {
        dispatch(new CheckSapB1Health())->afterResponse();
    }
});
```

### 2. Cache Health Results

```php
public function cachedHealthCheck(): array
{
    return Cache::remember('sap_b1_health', 30, function () {
        return collect($this->healthCheck->checkAll())
            ->map(fn($r) => $r->toArray())
            ->all();
    });
}
```

### 3. Use Timeouts

The health check uses the configured HTTP timeouts. For dedicated health checks, consider shorter timeouts:

```php
// Quick health check with custom timeout
// This uses the standard health check but relies on config timeouts
$result = $healthCheck->check();
```

## Troubleshooting

### Connection Failures

```
Error: Connection failed: cURL error 7: Failed to connect
```

- Verify the SAP B1 Service Layer URL is correct
- Check network connectivity and firewall rules
- Ensure SSL certificates are valid

### Authentication Failures

```
Error: Authentication failed: Invalid credentials
```

- Verify username and password
- Check if the user account is locked
- Ensure the user has Service Layer access

### Timeout Issues

```
Error: Health check failed: cURL error 28: Operation timed out
```

- Increase timeout in configuration
- Check SAP B1 server performance
- Verify network latency

## Next Steps

- [Artisan Commands](artisan-commands.md) - All CLI commands
- [Error Handling](error-handling.md) - Handle errors gracefully
- [Multiple Connections](multiple-connections.md) - Multi-connection setup
