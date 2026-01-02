<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response as Psr7Response;
use SapB1\Client\Response;

describe('Response', function (): void {
    it('gets status code', function (): void {
        $response = new Response(new Psr7Response(200));

        expect($response->status())->toBe(200);
    });

    it('detects successful response', function (): void {
        $response = new Response(new Psr7Response(200));

        expect($response->successful())->toBeTrue()
            ->and($response->failed())->toBeFalse();
    });

    it('detects client error', function (): void {
        $response = new Response(new Psr7Response(404));

        expect($response->clientError())->toBeTrue()
            ->and($response->failed())->toBeTrue();
    });

    it('detects server error', function (): void {
        $response = new Response(new Psr7Response(500));

        expect($response->serverError())->toBeTrue()
            ->and($response->failed())->toBeTrue();
    });

    it('gets json body', function (): void {
        $body = json_encode(['key' => 'value']);
        $response = new Response(new Psr7Response(200, [], $body));

        expect($response->json('key'))->toBe('value');
    });

    it('gets nested json value', function (): void {
        $body = json_encode(['outer' => ['inner' => 'value']]);
        $response = new Response(new Psr7Response(200, [], $body));

        expect($response->json('outer.inner'))->toBe('value');
    });

    it('returns default for missing json key', function (): void {
        $response = new Response(new Psr7Response(200, [], '{}'));

        expect($response->json('missing', 'default'))->toBe('default');
    });

    it('gets OData value array', function (): void {
        $body = json_encode([
            'value' => [
                ['CardCode' => 'C001'],
                ['CardCode' => 'C002'],
            ],
        ]);
        $response = new Response(new Psr7Response(200, [], $body));

        expect($response->value())->toHaveCount(2)
            ->and($response->hasValue())->toBeTrue();
    });

    it('gets single entity', function (): void {
        $body = json_encode(['CardCode' => 'C001', 'CardName' => 'Test']);
        $response = new Response(new Psr7Response(200, [], $body));

        expect($response->entity()['CardCode'])->toBe('C001')
            ->and($response->hasValue())->toBeFalse();
    });

    it('gets first entity from value array', function (): void {
        $body = json_encode([
            'value' => [
                ['CardCode' => 'C001'],
            ],
        ]);
        $response = new Response(new Psr7Response(200, [], $body));

        expect($response->entity()['CardCode'])->toBe('C001');
    });

    it('gets OData count', function (): void {
        $body = json_encode([
            'value' => [],
            'odata.count' => 100,
        ]);
        $response = new Response(new Psr7Response(200, [], $body));

        expect($response->count())->toBe(100);
    });

    it('gets OData count with @odata prefix', function (): void {
        $body = json_encode([
            'value' => [],
            '@odata.count' => 50,
        ]);
        $response = new Response(new Psr7Response(200, [], $body));

        expect($response->count())->toBe(50);
    });

    it('gets OData next link', function (): void {
        $body = json_encode([
            'value' => [],
            'odata.nextLink' => 'BusinessPartners?$skip=20',
        ]);
        $response = new Response(new Psr7Response(200, [], $body));

        expect($response->nextLink())->toBe('BusinessPartners?$skip=20')
            ->and($response->hasNextPage())->toBeTrue();
    });

    it('gets skip token from next link', function (): void {
        $body = json_encode([
            'value' => [],
            'odata.nextLink' => 'BusinessPartners?$skip=20&$top=10',
        ]);
        $response = new Response(new Psr7Response(200, [], $body));

        expect($response->skipToken())->toBe('20');
    });

    it('detects no next page', function (): void {
        $response = new Response(new Psr7Response(200, [], '{"value":[]}'));

        expect($response->hasNextPage())->toBeFalse()
            ->and($response->nextLink())->toBeNull();
    });

    it('gets error message', function (): void {
        $body = json_encode([
            'error' => [
                'message' => ['value' => 'Something went wrong'],
            ],
        ]);
        $response = new Response(new Psr7Response(400, [], $body));

        expect($response->errorMessage())->toBe('Something went wrong')
            ->and($response->hasError())->toBeTrue();
    });

    it('gets error code', function (): void {
        $body = json_encode([
            'error' => [
                'code' => '-1',
                'message' => 'Error',
            ],
        ]);
        $response = new Response(new Psr7Response(400, [], $body));

        expect($response->errorCode())->toBe('-1');
    });

    it('converts to array', function (): void {
        $body = json_encode(['key' => 'value']);
        $response = new Response(new Psr7Response(200, [], $body));

        expect($response->toArray())->toBe(['key' => 'value']);
    });

    it('converts to json', function (): void {
        $body = json_encode(['key' => 'value']);
        $response = new Response(new Psr7Response(200, [], $body));

        expect($response->toJson())->toBe('{"key":"value"}');
    });

    it('gets header', function (): void {
        $response = new Response(new Psr7Response(200, ['X-Custom' => 'value']));

        expect($response->header('X-Custom'))->toBe('value');
    });

    it('returns null for missing header', function (): void {
        $response = new Response(new Psr7Response(200));

        expect($response->header('X-Missing'))->toBeNull();
    });

    it('handles empty body', function (): void {
        $response = new Response(new Psr7Response(204));

        expect($response->json())->toBeNull()
            ->and($response->toArray())->toBeEmpty();
    });
});
