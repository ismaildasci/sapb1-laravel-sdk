<?php

declare(strict_types=1);

use SapB1\Exceptions\RateLimitException;

describe('Rate Limit Exception', function (): void {
    it('creates exception with default message', function (): void {
        $exception = new RateLimitException;

        expect($exception->getMessage())->toBe('Rate limit exceeded');
        expect($exception->getStatusCode())->toBe(429);
        expect($exception->getRetryAfter())->toBeNull();
    });

    it('creates exception with retry after', function (): void {
        $exception = new RateLimitException(
            message: 'Too many requests',
            retryAfter: 60
        );

        expect($exception->getMessage())->toBe('Too many requests');
        expect($exception->getRetryAfter())->toBe(60);
        expect($exception->context['retry_after'])->toBe(60);
    });

    it('includes context', function (): void {
        $exception = new RateLimitException(
            message: 'Rate limit exceeded',
            retryAfter: 30,
            context: ['endpoint' => 'BusinessPartners']
        );

        expect($exception->context)->toHaveKey('endpoint');
        expect($exception->context['endpoint'])->toBe('BusinessPartners');
        expect($exception->context['retry_after'])->toBe(30);
    });
});
