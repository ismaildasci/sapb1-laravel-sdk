<?php

declare(strict_types=1);

use SapB1\Exceptions\ProxyException;

describe('Proxy Exception', function (): void {
    it('creates exception with default values', function (): void {
        $exception = new ProxyException;

        expect($exception->getMessage())->toBe('Proxy error');
        expect($exception->getStatusCode())->toBe(502);
    });

    it('creates exception with custom message and status', function (): void {
        $exception = new ProxyException(
            message: 'Gateway timeout',
            statusCode: 504
        );

        expect($exception->getMessage())->toBe('Gateway timeout');
        expect($exception->getStatusCode())->toBe(504);
    });

    it('detects bad gateway error', function (): void {
        $exception = new ProxyException(statusCode: 502);

        expect($exception->isBadGateway())->toBeTrue();
        expect($exception->isGatewayTimeout())->toBeFalse();
    });

    it('detects gateway timeout error', function (): void {
        $exception = new ProxyException(statusCode: 504);

        expect($exception->isBadGateway())->toBeFalse();
        expect($exception->isGatewayTimeout())->toBeTrue();
    });

    it('includes context', function (): void {
        $exception = new ProxyException(
            message: 'Proxy error',
            statusCode: 502,
            context: ['endpoint' => 'Orders']
        );

        expect($exception->context)->toHaveKey('endpoint');
        expect($exception->context['endpoint'])->toBe('Orders');
    });
});
