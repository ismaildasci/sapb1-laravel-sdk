<?php

declare(strict_types=1);

namespace SapB1\Contracts;

/**
 * Interface for Circuit Breaker implementations.
 *
 * Circuit Breaker pattern prevents cascading failures by monitoring
 * the health of external services and temporarily blocking requests
 * when the service is deemed unhealthy.
 *
 * States:
 * - CLOSED: Normal operation, requests are allowed
 * - OPEN: Service is failing, requests are blocked
 * - HALF_OPEN: Testing if service has recovered
 */
interface CircuitBreakerInterface
{
    public const STATE_CLOSED = 'closed';

    public const STATE_OPEN = 'open';

    public const STATE_HALF_OPEN = 'half_open';

    /**
     * Get the current state of the circuit breaker.
     *
     * @param  string  $endpoint  The endpoint to check (default: '*' for global)
     */
    public function getState(string $endpoint = '*'): string;

    /**
     * Check if the circuit is allowing requests.
     *
     * @param  string  $endpoint  The endpoint to check (default: '*' for global)
     */
    public function isAvailable(string $endpoint = '*'): bool;

    /**
     * Record a successful request.
     *
     * This should be called when a request completes successfully (2xx response).
     * Slow but successful responses are still considered successful.
     *
     * @param  string  $endpoint  The endpoint (default: '*' for global)
     */
    public function recordSuccess(string $endpoint = '*'): void;

    /**
     * Record a failed request.
     *
     * This should ONLY be called for real errors:
     * - Connection timeouts
     * - 5xx status codes (500, 502, 503, 504)
     *
     * NOT for:
     * - Slow but successful responses (these are SUCCESS)
     * - 4xx client errors (user errors, not service failures)
     *
     * @param  string  $endpoint  The endpoint (default: '*' for global)
     */
    public function recordFailure(string $endpoint = '*'): void;

    /**
     * Reset the circuit breaker state to closed.
     *
     * @param  string  $endpoint  The endpoint (default: '*' for global)
     */
    public function reset(string $endpoint = '*'): void;

    /**
     * Get circuit breaker statistics.
     *
     * @param  string  $endpoint  The endpoint (default: '*' for global)
     * @return array<string, mixed>
     */
    public function getStats(string $endpoint = '*'): array;
}
