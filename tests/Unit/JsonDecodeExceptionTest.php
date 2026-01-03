<?php

declare(strict_types=1);

use SapB1\Exceptions\JsonDecodeException;

describe('JsonDecodeException', function (): void {
    it('creates exception with message and json error code', function (): void {
        $exception = new JsonDecodeException(
            message: 'Invalid JSON',
            jsonError: JSON_ERROR_SYNTAX
        );

        expect($exception->getMessage())->toBe('Invalid JSON');
        expect($exception->getJsonError())->toBe(JSON_ERROR_SYNTAX);
    });

    it('creates exception from last error', function (): void {
        // Trigger a JSON error
        $invalidJson = '{"invalid": json}';
        json_decode($invalidJson);

        $exception = JsonDecodeException::fromLastError($invalidJson);

        expect($exception->getMessage())->toContain('Failed to decode JSON');
        expect($exception->getJsonError())->toBe(JSON_ERROR_SYNTAX);
        expect($exception->context)->toHaveKey('body_preview');
        expect($exception->context)->toHaveKey('body_length');
    });

    it('includes context with json error details', function (): void {
        $exception = new JsonDecodeException(
            message: 'Test error',
            jsonError: JSON_ERROR_UTF8,
            context: ['extra' => 'data']
        );

        expect($exception->context)->toHaveKey('json_error');
        expect($exception->context['json_error'])->toBe(JSON_ERROR_UTF8);
        expect($exception->context)->toHaveKey('extra');
    });

    it('truncates body preview for large bodies', function (): void {
        $largeBody = str_repeat('x', 1000);
        json_decode('{invalid}');

        $exception = JsonDecodeException::fromLastError($largeBody);

        expect(strlen($exception->context['body_preview']))->toBeLessThanOrEqual(500);
    });
});
