# Artisan Commands

The SDK provides several Artisan commands for managing SAP B1 connections and sessions.

## Available Commands

| Command | Description |
|---------|-------------|
| `sap-b1:status` | Show connection status and configuration |
| `sap-b1:session` | Manage sessions (login, logout, refresh, clear) |
| `sap-b1:health` | Check connection health |
| `sap-b1:pool` | Manage session pool (status, warmup, drain, cleanup) |

## sap-b1:status

Display connection status, configuration, and session information.

```bash
# Show status for default connection
php artisan sap-b1:status

# Show status for specific connection
php artisan sap-b1:status --connection=production

# Include connection test
php artisan sap-b1:status --test
```

### Sample Output

```
SAP B1 Connection Status: default

  Configuration
  Base URL ...................................... https://server:50000
  Company DB .................................... SBODEMOUS
  Username ...................................... manager
  Language ...................................... 23

  Session Configuration
  Driver ........................................ file
  TTL ........................................... 1680 seconds
  Refresh Threshold ............................. 300 seconds

  HTTP Configuration
  Timeout ....................................... 30 seconds
  SSL Verify .................................... Yes
  Retry Times ................................... 3

  Session Status
  Status ........................................ Active
  Session ID .................................... abc123-def456
  Company ....................................... SBODEMOUS
  Expires At .................................... 2026-01-11 15:30:00
  Created At .................................... 2026-01-11 15:02:00
  Remaining ..................................... 28 minutes
```

### With Connection Test

```bash
php artisan sap-b1:status --test
```

```
  Connection Test
  Result ........................................ Connected successfully
  Response Time ................................. 234ms
  Company Name .................................. Demo US Company
```

## sap-b1:session

Manage SAP B1 sessions.

### Login

Create a new session:

```bash
php artisan sap-b1:session login

# Specific connection
php artisan sap-b1:session login --connection=production
```

### Logout

End the current session:

```bash
php artisan sap-b1:session logout

# Skip confirmation
php artisan sap-b1:session logout --force

# Specific connection
php artisan sap-b1:session logout --connection=production
```

### Refresh

Refresh the current session:

```bash
php artisan sap-b1:session refresh

php artisan sap-b1:session refresh --connection=production
```

### Clear

Clear local session data without SAP logout:

```bash
php artisan sap-b1:session clear

php artisan sap-b1:session clear --force
```

### Clear All

Clear all stored sessions:

```bash
php artisan sap-b1:session clear-all

php artisan sap-b1:session clear-all --force
```

## sap-b1:health

Check SAP B1 connection health.

```bash
# Check default connection
php artisan sap-b1:health

# Check specific connection
php artisan sap-b1:health --connection=production

# Check all connections
php artisan sap-b1:health --all

# JSON output for monitoring tools
php artisan sap-b1:health --json
```

### Sample Output

```
Checking SAP B1 connection health...

  SAP B1 connection is healthy

  Status ........................................ Healthy
  Connection .................................... default
  Company DB .................................... SBODEMOUS
  Response Time ................................. 156.42ms
  Session ID .................................... abc123...
```

### JSON Output

```bash
php artisan sap-b1:health --json
```

```json
{
    "healthy": true,
    "message": "SAP B1 connection is healthy",
    "connection": "default",
    "company_db": "SBODEMOUS",
    "response_time": 156.42,
    "session_id": "abc123..."
}
```

### Check All Connections

```bash
php artisan sap-b1:health --all
```

```
Checking all SAP B1 connections...

  default ....................................... Healthy
    Company ..................................... SBODEMOUS
    Response .................................... 156.42ms

  production .................................... Healthy
    Company ..................................... SBOPROD
    Response .................................... 89.21ms

  warehouse ..................................... Unhealthy
    Error ....................................... Connection failed: timeout

Some connections are unhealthy.
```

## sap-b1:pool

Manage the session pool (requires `SAP_B1_POOL_ENABLED=true`).

### Status

View pool statistics:

```bash
php artisan sap-b1:pool status

php artisan sap-b1:pool status --connection=production
```

```
Session Pool Status [default]

  Total Sessions ................................ 5
  Active (In Use) ............................... 2
  Idle (Available) .............................. 3
  Expired ....................................... 0

  Min Size ...................................... 2
  Max Size ...................................... 10
  Algorithm ..................................... round_robin

  Pool Health ................................... Healthy
  Utilization ................................... 40.0%
```

### Warmup

Pre-create sessions in the pool:

```bash
# Warmup to minimum size
php artisan sap-b1:pool warmup

# Warmup specific count
php artisan sap-b1:pool warmup --count=5

# Specific connection
php artisan sap-b1:pool warmup --connection=production --count=10
```

### Drain

Remove all sessions from the pool:

```bash
php artisan sap-b1:pool drain

# Skip confirmation
php artisan sap-b1:pool drain --force

# Specific connection
php artisan sap-b1:pool drain --connection=production
```

### Cleanup

Remove expired sessions:

```bash
php artisan sap-b1:pool cleanup

php artisan sap-b1:pool cleanup --connection=production
```

### Sessions

View session summary:

```bash
php artisan sap-b1:pool sessions
```

```
Sessions in Pool [default]

+---------+-------+
| Status  | Count |
+---------+-------+
| idle    | 3     |
| active  | 2     |
| expired | 0     |
+---------+-------+

  Total ......................................... 5
```

## Exit Codes

All commands return appropriate exit codes:

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Failure |

Use exit codes in scripts:

```bash
php artisan sap-b1:health --connection=production
if [ $? -eq 0 ]; then
    echo "SAP B1 is healthy"
else
    echo "SAP B1 is down!"
    # Send alert
fi
```

## Scheduling Commands

Schedule health checks in `routes/console.php` (Laravel 11+):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('sap-b1:health --all --json')
    ->everyFiveMinutes()
    ->appendOutputTo(storage_path('logs/sap-b1-health.log'));

Schedule::command('sap-b1:pool cleanup')
    ->everyMinute()
    ->withoutOverlapping();
```

Or in `app/Console/Kernel.php` (Laravel 10):

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('sap-b1:health --all')
        ->everyFiveMinutes()
        ->emailOutputOnFailure('admin@example.com');

    $schedule->command('sap-b1:pool cleanup')
        ->everyMinute()
        ->withoutOverlapping();
}
```

## CI/CD Integration

### Health Check in Deployment

```yaml
# .github/workflows/deploy.yml
- name: Check SAP B1 Connection
  run: php artisan sap-b1:health --json
  continue-on-error: false
```

### Pre-deployment Validation

```bash
#!/bin/bash
# deploy.sh

echo "Checking SAP B1 connections..."
php artisan sap-b1:health --all

if [ $? -ne 0 ]; then
    echo "SAP B1 health check failed. Aborting deployment."
    exit 1
fi

echo "All connections healthy. Proceeding with deployment..."
```

## Next Steps

- [Health Checks](health-checks.md) - Programmatic health checks
- [Session Pool](session-pool.md) - Configure session pooling
- [Configuration](configuration.md) - All configuration options
