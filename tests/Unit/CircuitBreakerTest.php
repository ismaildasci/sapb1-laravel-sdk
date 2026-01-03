<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use SapB1\Client\CircuitBreaker;
use SapB1\Contracts\CircuitBreakerInterface;

beforeEach(function (): void {
    Cache::flush();
});

describe('CircuitBreaker', function (): void {
    it('starts in closed state', function (): void {
        $cb = new CircuitBreaker(failureThreshold: 5);

        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_CLOSED);
        expect($cb->isAvailable())->toBeTrue();
    });

    it('opens after failure threshold is reached', function (): void {
        $cb = new CircuitBreaker(failureThreshold: 3);

        $cb->recordFailure();
        $cb->recordFailure();
        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_CLOSED);

        $cb->recordFailure();
        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_OPEN);
        expect($cb->isAvailable())->toBeFalse();
    });

    it('resets failure count on success in closed state', function (): void {
        $cb = new CircuitBreaker(failureThreshold: 3);

        $cb->recordFailure();
        $cb->recordFailure();
        $cb->recordSuccess();

        // Failure count should be reset
        $stats = $cb->getStats();
        expect($stats['failures'])->toBe(0);

        // Should not open after 2 more failures (total 2 not 4)
        $cb->recordFailure();
        $cb->recordFailure();
        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_CLOSED);

        // Third failure should open it
        $cb->recordFailure();
        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_OPEN);
    });

    it('can be reset to closed state', function (): void {
        $cb = new CircuitBreaker(failureThreshold: 2);

        $cb->recordFailure();
        $cb->recordFailure();
        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_OPEN);

        $cb->reset();
        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_CLOSED);
        expect($cb->isAvailable())->toBeTrue();
    });

    it('tracks per-endpoint state', function (): void {
        $cb = new CircuitBreaker(failureThreshold: 2);

        $cb->recordFailure('Orders');
        $cb->recordFailure('Orders');

        expect($cb->getState('Orders'))->toBe(CircuitBreakerInterface::STATE_OPEN);
        expect($cb->getState('BusinessPartners'))->toBe(CircuitBreakerInterface::STATE_CLOSED);
        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_CLOSED);
    });

    it('tracks total successes and failures', function (): void {
        $cb = new CircuitBreaker(failureThreshold: 10);

        $cb->recordSuccess();
        $cb->recordSuccess();
        $cb->recordFailure();
        $cb->recordSuccess();

        $stats = $cb->getStats();

        expect($stats['total_successes'])->toBe(3);
        expect($stats['total_failures'])->toBe(1);
    });

    it('tracks last success and failure timestamps', function (): void {
        $cb = new CircuitBreaker(failureThreshold: 10);

        $beforeSuccess = time();
        $cb->recordSuccess();
        $afterSuccess = time();

        $stats = $cb->getStats();

        expect($stats['last_success_at'])->toBeGreaterThanOrEqual($beforeSuccess);
        expect($stats['last_success_at'])->toBeLessThanOrEqual($afterSuccess);
    });

    it('uses config values when not provided', function (): void {
        config(['sap-b1.http.circuit_breaker.failure_threshold' => 7]);

        $cb = new CircuitBreaker;

        // Record 6 failures - should still be closed
        for ($i = 0; $i < 6; $i++) {
            $cb->recordFailure();
        }
        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_CLOSED);

        // 7th failure should open
        $cb->recordFailure();
        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_OPEN);
    });
});

describe('CircuitBreaker Half-Open State', function (): void {
    it('transitions to half-open after open duration', function (): void {
        $cb = new CircuitBreaker(
            failureThreshold: 2,
            openDuration: 1 // 1 second for testing
        );

        $cb->recordFailure();
        $cb->recordFailure();
        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_OPEN);

        sleep(2);

        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_HALF_OPEN);
        expect($cb->isAvailable())->toBeTrue();
    });

    it('closes after successful half-open attempts', function (): void {
        $cb = new CircuitBreaker(
            failureThreshold: 2,
            openDuration: 1,
            halfOpenMaxAttempts: 2
        );

        $cb->recordFailure();
        $cb->recordFailure();

        sleep(2);
        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_HALF_OPEN);

        $cb->recordSuccess();
        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_HALF_OPEN);

        $cb->recordSuccess();
        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_CLOSED);
    });

    it('reopens on failure during half-open', function (): void {
        $cb = new CircuitBreaker(
            failureThreshold: 2,
            openDuration: 1
        );

        $cb->recordFailure();
        $cb->recordFailure();

        sleep(2);
        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_HALF_OPEN);

        $cb->recordFailure();
        expect($cb->getState())->toBe(CircuitBreakerInterface::STATE_OPEN);
    });
});
