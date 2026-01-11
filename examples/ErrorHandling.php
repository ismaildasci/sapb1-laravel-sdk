<?php

declare(strict_types=1);

/**
 * Error Handling with SAP Business One Laravel SDK
 *
 * This example demonstrates how to handle various error scenarios
 * including authentication errors, validation errors, and network issues.
 */

use SapB1\Exceptions\AuthenticationException;
use SapB1\Exceptions\CircuitBreakerOpenException;
use SapB1\Exceptions\ConnectionException;
use SapB1\Exceptions\JsonDecodeException;
use SapB1\Exceptions\PoolExhaustedException;
use SapB1\Exceptions\ProxyException;
use SapB1\Exceptions\RateLimitException;
use SapB1\Exceptions\ServiceLayerException;
use SapB1\Exceptions\SessionExpiredException;
use SapB1\Facades\SapB1;

// =============================================================================
// COMPREHENSIVE ERROR HANDLING
// =============================================================================

try {
    $response = SapB1::create('BusinessPartners', [
        'CardCode' => 'C001',
        'CardName' => 'Test Customer',
    ]);
} catch (AuthenticationException $e) {
    // Invalid credentials or login failed
    echo 'Authentication failed: '.$e->getMessage().PHP_EOL;
    // Suggestion: Check SAP_B1_USERNAME and SAP_B1_PASSWORD in .env

} catch (SessionExpiredException $e) {
    // Session timed out (usually auto-refreshed, but can occur)
    echo 'Session expired: '.$e->getMessage().PHP_EOL;
    // SDK will automatically retry with a new session if auto_refresh is enabled

} catch (RateLimitException $e) {
    // Too many requests (429)
    echo 'Rate limited! Retry after: '.$e->getRetryAfter().' seconds'.PHP_EOL;
    sleep($e->getRetryAfter());
    // Retry the request...

} catch (CircuitBreakerOpenException $e) {
    // Circuit breaker is open due to repeated failures
    echo 'Service temporarily unavailable (circuit open)'.PHP_EOL;
    echo 'Will recover after: '.$e->getRecoveryTime()->diffForHumans().PHP_EOL;
    // Don't retry immediately - the circuit breaker protects the server

} catch (PoolExhaustedException $e) {
    // No sessions available in pool (high concurrency scenario)
    echo 'No sessions available, try again later'.PHP_EOL;
    // Consider increasing pool size or wait_timeout in config

} catch (ProxyException $e) {
    // Proxy/gateway error (502)
    echo 'Proxy error: '.$e->getMessage().PHP_EOL;
    // Usually temporary - SDK will retry automatically

} catch (ConnectionException $e) {
    // Network connectivity issues
    echo 'Cannot connect to SAP server: '.$e->getMessage().PHP_EOL;
    // Check network, firewall, VPN, or SAP server status

} catch (ServiceLayerException $e) {
    // SAP Business logic error
    echo 'SAP Error Code: '.$e->getCode().PHP_EOL;
    echo 'SAP Message: '.$e->getMessage().PHP_EOL;
    echo 'Human Message: '.$e->getHumanMessage().PHP_EOL;
    echo 'Suggestion: '.$e->getSuggestion().PHP_EOL;
    echo 'Category: '.$e->getCategory().PHP_EOL;
    echo 'Is Retryable: '.($e->isRetryable() ? 'Yes' : 'No').PHP_EOL;

} catch (JsonDecodeException $e) {
    // Malformed JSON response
    echo 'Invalid JSON response: '.$e->getMessage().PHP_EOL;
    echo 'Body preview: '.$e->getBodyPreview().PHP_EOL;

} catch (\Throwable $e) {
    // Catch-all for unexpected errors
    echo 'Unexpected error: '.$e->getMessage().PHP_EOL;
}

// =============================================================================
// ERROR HANDLING FOR CRUD OPERATIONS
// =============================================================================

// CREATE - Handle duplicate key error
try {
    SapB1::create('BusinessPartners', [
        'CardCode' => 'EXISTING_CODE',
        'CardName' => 'Test',
    ]);
} catch (ServiceLayerException $e) {
    if ($e->getCode() === -2035) {
        echo 'Business partner already exists, updating instead...'.PHP_EOL;
        SapB1::update('BusinessPartners', 'EXISTING_CODE', [
            'CardName' => 'Test',
        ]);
    } else {
        throw $e;
    }
}

// UPDATE - Handle not found error
try {
    SapB1::update('BusinessPartners', 'NONEXISTENT', ['CardName' => 'New Name']);
} catch (ServiceLayerException $e) {
    if ($e->getCode() === -2028) {
        echo 'Business partner not found: NONEXISTENT'.PHP_EOL;
    } else {
        throw $e;
    }
}

// DELETE - Handle constraint violation
try {
    SapB1::delete('BusinessPartners', 'C001');
} catch (ServiceLayerException $e) {
    if ($e->getCategory() === 'constraint_violation') {
        echo 'Cannot delete: '.$e->getHumanMessage().PHP_EOL;
        echo 'Tip: '.$e->getSuggestion().PHP_EOL;
    } else {
        throw $e;
    }
}

// =============================================================================
// VALIDATION BEFORE REQUEST
// =============================================================================

function createBusinessPartner(array $data): array
{
    // Validate required fields before making the API call
    $required = ['CardCode', 'CardName', 'CardType'];
    $missing = array_diff($required, array_keys($data));

    if (! empty($missing)) {
        throw new \InvalidArgumentException(
            'Missing required fields: '.implode(', ', $missing)
        );
    }

    // Validate CardType
    $validTypes = ['cCustomer', 'cSupplier', 'cLead'];
    if (! in_array($data['CardType'], $validTypes, true)) {
        throw new \InvalidArgumentException(
            'Invalid CardType. Must be one of: '.implode(', ', $validTypes)
        );
    }

    return SapB1::create('BusinessPartners', $data);
}

// =============================================================================
// RETRY PATTERN FOR TRANSIENT ERRORS
// =============================================================================

function withRetry(callable $operation, int $maxAttempts = 3): mixed
{
    $attempt = 0;
    $lastException = null;

    while ($attempt < $maxAttempts) {
        try {
            return $operation();
        } catch (ServiceLayerException $e) {
            $lastException = $e;

            if (! $e->isRetryable()) {
                throw $e; // Don't retry non-retryable errors
            }

            $attempt++;
            if ($attempt < $maxAttempts) {
                $delay = pow(2, $attempt); // Exponential backoff
                echo "Attempt {$attempt} failed, retrying in {$delay}s...".PHP_EOL;
                sleep($delay);
            }
        }
    }

    throw $lastException;
}

// Usage
$order = withRetry(fn () => SapB1::create('Orders', [
    'CardCode' => 'C001',
    'DocumentLines' => [
        ['ItemCode' => 'A001', 'Quantity' => 1],
    ],
]));

// =============================================================================
// LOGGING ERRORS
// =============================================================================

use Illuminate\Support\Facades\Log;

try {
    $response = SapB1::get('BusinessPartners');
} catch (ServiceLayerException $e) {
    Log::error('SAP B1 Error', [
        'code' => $e->getCode(),
        'message' => $e->getMessage(),
        'human_message' => $e->getHumanMessage(),
        'category' => $e->getCategory(),
        'suggestion' => $e->getSuggestion(),
        'trace' => $e->getTraceAsString(),
    ]);

    throw $e;
}

// =============================================================================
// ERROR RECOVERY STRATEGIES
// =============================================================================

// Strategy 1: Fallback to cached data
function getBusinessPartnersWithFallback(): array
{
    try {
        $partners = SapB1::get('BusinessPartners')->value();
        cache()->put('business_partners', $partners, 3600);

        return $partners;
    } catch (ConnectionException|CircuitBreakerOpenException $e) {
        $cached = cache()->get('business_partners');
        if ($cached !== null) {
            Log::warning('Using cached business partners due to: '.$e->getMessage());

            return $cached;
        }
        throw $e;
    }
}

// Strategy 2: Queue for later processing
function createOrderWithFallback(array $orderData): void
{
    try {
        SapB1::create('Orders', $orderData);
    } catch (ConnectionException|CircuitBreakerOpenException $e) {
        // Queue the order for later processing
        dispatch(new \App\Jobs\CreateSapOrder($orderData));
        Log::info('Order queued for later processing', ['data' => $orderData]);
    }
}
