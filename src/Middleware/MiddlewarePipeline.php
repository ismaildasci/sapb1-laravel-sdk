<?php

declare(strict_types=1);

namespace SapB1\Middleware;

use Closure;
use SapB1\Client\PendingRequest;
use SapB1\Client\Response;
use SapB1\Contracts\MiddlewareInterface;

class MiddlewarePipeline
{
    /** @var array<int, MiddlewareInterface|Closure> */
    protected array $middleware = [];

    /**
     * Add middleware to the pipeline.
     *
     * @param  MiddlewareInterface|Closure(PendingRequest, Closure): Response  $middleware
     */
    public function push(MiddlewareInterface|Closure $middleware): self
    {
        $this->middleware[] = $middleware;

        return $this;
    }

    /**
     * Prepend middleware to the pipeline.
     *
     * @param  MiddlewareInterface|Closure(PendingRequest, Closure): Response  $middleware
     */
    public function prepend(MiddlewareInterface|Closure $middleware): self
    {
        array_unshift($this->middleware, $middleware);

        return $this;
    }

    /**
     * Remove middleware from the pipeline.
     *
     * @param  class-string<MiddlewareInterface>  $middlewareClass
     */
    public function remove(string $middlewareClass): self
    {
        $this->middleware = array_filter(
            $this->middleware,
            fn ($m) => ! $m instanceof $middlewareClass
        );

        return $this;
    }

    /**
     * Get all middleware in the pipeline.
     *
     * @return array<int, MiddlewareInterface|Closure>
     */
    public function all(): array
    {
        return $this->middleware;
    }

    /**
     * Clear all middleware from the pipeline.
     */
    public function clear(): self
    {
        $this->middleware = [];

        return $this;
    }

    /**
     * Process the request through all middleware.
     *
     * @param  Closure(PendingRequest): Response  $destination
     */
    public function process(PendingRequest $request, Closure $destination): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            $this->buildCarry(),
            $destination
        );

        return $pipeline($request);
    }

    /**
     * Build the middleware carry function.
     *
     * @return Closure(Closure, MiddlewareInterface|Closure): Closure
     */
    protected function buildCarry(): Closure
    {
        return function (Closure $next, MiddlewareInterface|Closure $middleware): Closure {
            return function (PendingRequest $request) use ($next, $middleware): Response {
                if ($middleware instanceof MiddlewareInterface) {
                    return $middleware->handle($request, $next);
                }

                return $middleware($request, $next);
            };
        };
    }
}
