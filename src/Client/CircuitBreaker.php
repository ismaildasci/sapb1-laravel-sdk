<?php

declare(strict_types=1);

namespace SapB1\Client;

use Illuminate\Support\Facades\Cache;
use SapB1\Contracts\CircuitBreakerInterface;
use SapB1\Events\CircuitBreakerStateChanged;

/**
 * Circuit Breaker implementation using Laravel Cache.
 *
 * Prevents cascading failures by tracking service health and
 * temporarily blocking requests when too many failures occur.
 */
class CircuitBreaker implements CircuitBreakerInterface
{
    /**
     * Cache key prefix for circuit breaker state.
     */
    protected string $cachePrefix = 'sap_b1_circuit_breaker:';

    /**
     * Number of consecutive failures before opening the circuit.
     */
    protected int $failureThreshold;

    /**
     * Seconds to wait before trying half-open state.
     */
    protected int $openDuration;

    /**
     * Number of successful requests in half-open to close circuit.
     */
    protected int $halfOpenMaxAttempts;

    /**
     * Create a new CircuitBreaker instance.
     */
    public function __construct(
        ?int $failureThreshold = null,
        ?int $openDuration = null,
        ?int $halfOpenMaxAttempts = null
    ) {
        $this->failureThreshold = $failureThreshold
            ?? (int) config('sap-b1.http.circuit_breaker.failure_threshold', 5);
        $this->openDuration = $openDuration
            ?? (int) config('sap-b1.http.circuit_breaker.open_duration', 30);
        $this->halfOpenMaxAttempts = $halfOpenMaxAttempts
            ?? (int) config('sap-b1.http.circuit_breaker.half_open_max_attempts', 3);
    }

    /**
     * Get the current state of the circuit breaker.
     */
    public function getState(string $endpoint = '*'): string
    {
        $data = $this->getData($endpoint);

        // Check if circuit was opened and needs to transition to half-open
        if ($data['state'] === self::STATE_OPEN) {
            $openedAt = $data['opened_at'] ?? 0;
            if (time() - $openedAt >= $this->openDuration) {
                $this->transitionToHalfOpen($endpoint);

                return self::STATE_HALF_OPEN;
            }
        }

        return $data['state'];
    }

    /**
     * Check if the circuit is allowing requests.
     */
    public function isAvailable(string $endpoint = '*'): bool
    {
        $state = $this->getState($endpoint);

        return $state !== self::STATE_OPEN;
    }

    /**
     * Record a successful request.
     *
     * Slow but successful responses should still call this method.
     */
    public function recordSuccess(string $endpoint = '*'): void
    {
        $data = $this->getData($endpoint);

        if ($data['state'] === self::STATE_HALF_OPEN) {
            $data['half_open_successes'] = ($data['half_open_successes'] ?? 0) + 1;

            // Transition back to closed after enough successful half-open attempts
            if ($data['half_open_successes'] >= $this->halfOpenMaxAttempts) {
                $this->transitionToClosed($endpoint);

                return;
            }
        }

        // Reset failure count on success in closed state
        if ($data['state'] === self::STATE_CLOSED) {
            $data['failures'] = 0;
        }

        $data['last_success_at'] = time();
        $data['total_successes'] = ($data['total_successes'] ?? 0) + 1;

        $this->setData($endpoint, $data);
    }

    /**
     * Record a failed request.
     *
     * Only call this for real errors:
     * - Connection timeouts
     * - 5xx status codes (500, 502, 503, 504)
     */
    public function recordFailure(string $endpoint = '*'): void
    {
        $data = $this->getData($endpoint);

        $data['failures'] = ($data['failures'] ?? 0) + 1;
        $data['last_failure_at'] = time();
        $data['total_failures'] = ($data['total_failures'] ?? 0) + 1;

        // Check if we need to open the circuit
        if ($data['state'] === self::STATE_CLOSED && $data['failures'] >= $this->failureThreshold) {
            $this->transitionToOpen($endpoint);

            return;
        }

        // If half-open and failure, go back to open
        if ($data['state'] === self::STATE_HALF_OPEN) {
            $this->transitionToOpen($endpoint);

            return;
        }

        $this->setData($endpoint, $data);
    }

    /**
     * Reset the circuit breaker state to closed.
     */
    public function reset(string $endpoint = '*'): void
    {
        $this->transitionToClosed($endpoint);
    }

    /**
     * Get circuit breaker statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(string $endpoint = '*'): array
    {
        $data = $this->getData($endpoint);

        return [
            'state' => $this->getState($endpoint),
            'failures' => $data['failures'] ?? 0,
            'total_successes' => $data['total_successes'] ?? 0,
            'total_failures' => $data['total_failures'] ?? 0,
            'last_success_at' => $data['last_success_at'] ?? null,
            'last_failure_at' => $data['last_failure_at'] ?? null,
            'opened_at' => $data['opened_at'] ?? null,
            'half_open_successes' => $data['half_open_successes'] ?? 0,
        ];
    }

    /**
     * Transition to open state.
     */
    protected function transitionToOpen(string $endpoint): void
    {
        $data = $this->getData($endpoint);
        $previousState = $data['state'];

        $data['state'] = self::STATE_OPEN;
        $data['opened_at'] = time();
        $data['half_open_successes'] = 0;

        $this->setData($endpoint, $data);
        $this->dispatchStateChanged($endpoint, $previousState, self::STATE_OPEN, $data['failures'] ?? 0);
    }

    /**
     * Transition to half-open state.
     */
    protected function transitionToHalfOpen(string $endpoint): void
    {
        $data = $this->getData($endpoint);
        $previousState = $data['state'];

        $data['state'] = self::STATE_HALF_OPEN;
        $data['half_open_successes'] = 0;

        $this->setData($endpoint, $data);
        $this->dispatchStateChanged($endpoint, $previousState, self::STATE_HALF_OPEN, $data['failures'] ?? 0);
    }

    /**
     * Transition to closed state.
     */
    protected function transitionToClosed(string $endpoint): void
    {
        $existingData = $this->getData($endpoint);
        $previousState = $existingData['state'];

        $data = [
            'state' => self::STATE_CLOSED,
            'failures' => 0,
            'half_open_successes' => 0,
            'total_successes' => $existingData['total_successes'] ?? 0,
            'total_failures' => $existingData['total_failures'] ?? 0,
            'last_success_at' => $existingData['last_success_at'] ?? null,
            'last_failure_at' => $existingData['last_failure_at'] ?? null,
        ];

        $this->setData($endpoint, $data);

        if ($previousState !== self::STATE_CLOSED) {
            $this->dispatchStateChanged($endpoint, $previousState, self::STATE_CLOSED, 0);
        }
    }

    /**
     * Dispatch state changed event.
     */
    protected function dispatchStateChanged(
        string $endpoint,
        string $previousState,
        string $newState,
        int $failureCount
    ): void {
        if (class_exists(CircuitBreakerStateChanged::class)) {
            event(new CircuitBreakerStateChanged($endpoint, $previousState, $newState, $failureCount));
        }
    }

    /**
     * Get circuit breaker data from cache.
     *
     * @return array<string, mixed>
     */
    protected function getData(string $endpoint): array
    {
        $key = $this->getCacheKey($endpoint);

        /** @var array<string, mixed>|null $data */
        $data = Cache::get($key);

        if ($data === null) {
            return [
                'state' => self::STATE_CLOSED,
                'failures' => 0,
                'half_open_successes' => 0,
            ];
        }

        return $data;
    }

    /**
     * Set circuit breaker data in cache.
     *
     * @param  array<string, mixed>  $data
     */
    protected function setData(string $endpoint, array $data): void
    {
        $key = $this->getCacheKey($endpoint);
        Cache::put($key, $data, 3600); // 1 hour TTL
    }

    /**
     * Get the cache key for an endpoint.
     */
    protected function getCacheKey(string $endpoint): string
    {
        return $this->cachePrefix.md5($endpoint);
    }
}
