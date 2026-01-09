<?php

declare(strict_types=1);

namespace SapB1\Contracts;

use Closure;
use SapB1\Client\PendingRequest;
use SapB1\Client\Response;

interface MiddlewareInterface
{
    /**
     * Handle the request through the middleware.
     *
     * @param  Closure(PendingRequest): Response  $next
     */
    public function handle(PendingRequest $request, Closure $next): Response;
}
