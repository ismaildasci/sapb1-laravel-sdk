<?php

declare(strict_types=1);

use SapB1\Exceptions\CircuitBreakerOpenException;

describe('CircuitBreakerOpenException', function (): void {
    it('creates exception with default values', function (): void {
        $exception = new CircuitBreakerOpenException;

        expect($exception->getMessage())->toBe('Circuit breaker is open');
        expect($exception->getEndpoint())->toBe('*');
        expect($exception->getRetryAfter())->toBe(30);
        expect($exception->getCode())->toBe(503);
    });

    it('creates exception with custom values', function (): void {
        $exception = new CircuitBreakerOpenException(
            endpoint: 'Orders',
            retryAfter: 60,
            message: 'Service unavailable'
        );

        expect($exception->getMessage())->toBe('Service unavailable');
        expect($exception->getEndpoint())->toBe('Orders');
        expect($exception->getRetryAfter())->toBe(60);
    });

    it('includes context data', function (): void {
        $exception = new CircuitBreakerOpenException(
            endpoint: 'BusinessPartners',
            retryAfter: 45,
            context: ['connection' => 'default']
        );

        expect($exception->context)->toHaveKey('endpoint');
        expect($exception->context['endpoint'])->toBe('BusinessPartners');
        expect($exception->context['retry_after'])->toBe(45);
        expect($exception->context['connection'])->toBe('default');
    });
});
