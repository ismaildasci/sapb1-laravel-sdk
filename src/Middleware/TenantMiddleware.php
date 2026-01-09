<?php

declare(strict_types=1);

namespace SapB1\Middleware;

use Closure;
use SapB1\Client\PendingRequest;
use SapB1\Client\Response;
use SapB1\Contracts\MiddlewareInterface;

class TenantMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected string $tenantId,
        protected ?string $tenantHeader = 'X-Tenant-ID'
    ) {}

    public function handle(PendingRequest $request, Closure $next): Response
    {
        if ($this->tenantHeader) {
            $request->withHeader($this->tenantHeader, $this->tenantId);
        }

        return $next($request);
    }
}
