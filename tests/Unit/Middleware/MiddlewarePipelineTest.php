<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use SapB1\Client\PendingRequest;
use SapB1\Client\Response;
use SapB1\Contracts\MiddlewareInterface;
use SapB1\Middleware\MiddlewarePipeline;

beforeEach(function (): void {
    $this->pipeline = new MiddlewarePipeline;
});

describe('MiddlewarePipeline', function (): void {
    it('processes request through empty pipeline', function (): void {
        $request = Mockery::mock(PendingRequest::class);
        $guzzleResponse = new GuzzleResponse(200, [], '{}');
        $response = new Response($guzzleResponse);

        $result = $this->pipeline->process($request, fn () => $response);

        expect($result)->toBe($response);
    });

    it('can push middleware to pipeline', function (): void {
        $middleware = fn ($request, $next) => $next($request);

        $this->pipeline->push($middleware);

        expect($this->pipeline->all())->toHaveCount(1);
    });

    it('can prepend middleware to pipeline', function (): void {
        $first = fn ($request, $next) => $next($request);
        $second = fn ($request, $next) => $next($request);

        $this->pipeline->push($first);
        $this->pipeline->prepend($second);

        $all = $this->pipeline->all();
        expect($all[0])->toBe($second);
        expect($all[1])->toBe($first);
    });

    it('can remove middleware by class', function (): void {
        $middleware = new class implements MiddlewareInterface
        {
            public function handle(PendingRequest $request, Closure $next): Response
            {
                return $next($request);
            }
        };

        $this->pipeline->push($middleware);
        expect($this->pipeline->all())->toHaveCount(1);

        $this->pipeline->remove($middleware::class);
        expect($this->pipeline->all())->toHaveCount(0);
    });

    it('can clear all middleware', function (): void {
        $this->pipeline->push(fn ($request, $next) => $next($request));
        $this->pipeline->push(fn ($request, $next) => $next($request));

        expect($this->pipeline->all())->toHaveCount(2);

        $this->pipeline->clear();

        expect($this->pipeline->all())->toHaveCount(0);
    });

    it('executes middleware in correct order', function (): void {
        $order = [];

        $this->pipeline->push(function ($request, $next) use (&$order) {
            $order[] = 'first_before';
            $response = $next($request);
            $order[] = 'first_after';

            return $response;
        });

        $this->pipeline->push(function ($request, $next) use (&$order) {
            $order[] = 'second_before';
            $response = $next($request);
            $order[] = 'second_after';

            return $response;
        });

        $request = Mockery::mock(PendingRequest::class);
        $guzzleResponse = new GuzzleResponse(200, [], '{}');

        $this->pipeline->process($request, function () use (&$order, $guzzleResponse) {
            $order[] = 'destination';

            return new Response($guzzleResponse);
        });

        expect($order)->toBe([
            'first_before',
            'second_before',
            'destination',
            'second_after',
            'first_after',
        ]);
    });

    it('supports MiddlewareInterface implementations', function (): void {
        $called = false;

        $middleware = new class($called) implements MiddlewareInterface
        {
            public function __construct(private bool &$called) {}

            public function handle(PendingRequest $request, Closure $next): Response
            {
                $this->called = true;

                return $next($request);
            }
        };

        $this->pipeline->push($middleware);

        $request = Mockery::mock(PendingRequest::class);
        $guzzleResponse = new GuzzleResponse(200, [], '{}');

        $this->pipeline->process($request, fn () => new Response($guzzleResponse));

        expect($called)->toBeTrue();
    });

    it('can short-circuit the pipeline', function (): void {
        $guzzleResponse = new GuzzleResponse(403, [], '{"error": "Forbidden"}');
        $earlyResponse = new Response($guzzleResponse);
        $destinationCalled = false;

        $this->pipeline->push(function ($request, $next) use ($earlyResponse) {
            // Don't call $next, return early
            return $earlyResponse;
        });

        $request = Mockery::mock(PendingRequest::class);

        $result = $this->pipeline->process($request, function () use (&$destinationCalled) {
            $destinationCalled = true;

            return new Response(new GuzzleResponse(200, [], '{}'));
        });

        expect($result)->toBe($earlyResponse);
        expect($destinationCalled)->toBeFalse();
    });

    it('returns fluent interface from push', function (): void {
        $result = $this->pipeline->push(fn ($r, $n) => $n($r));

        expect($result)->toBe($this->pipeline);
    });

    it('returns fluent interface from prepend', function (): void {
        $result = $this->pipeline->prepend(fn ($r, $n) => $n($r));

        expect($result)->toBe($this->pipeline);
    });

    it('returns fluent interface from remove', function (): void {
        $result = $this->pipeline->remove(MiddlewareInterface::class);

        expect($result)->toBe($this->pipeline);
    });

    it('returns fluent interface from clear', function (): void {
        $result = $this->pipeline->clear();

        expect($result)->toBe($this->pipeline);
    });
});
