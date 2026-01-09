<?php

declare(strict_types=1);

namespace SapB1\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use SapB1\Client\PendingRequest;
use SapB1\Client\Response;
use SapB1\Contracts\MiddlewareInterface;

class LoggingMiddleware implements MiddlewareInterface
{
    public function __construct(
        protected string $channel = 'sap-b1',
        protected bool $logBody = false
    ) {}

    public function handle(PendingRequest $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $requestId = $request->getRequestId() ?? uniqid('sap_');

        Log::channel($this->channel)->debug('SAP B1 Request', [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'endpoint' => $request->getEndpoint(),
            'query' => $request->getQueryString(),
        ]);

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $logData = [
            'request_id' => $requestId,
            'status' => $response->status(),
            'duration_ms' => $duration,
        ];

        if ($response->failed()) {
            $logData['error'] = $response->errorMessage();
            Log::channel($this->channel)->error('SAP B1 Request Failed', $logData);
        } else {
            Log::channel($this->channel)->debug('SAP B1 Response', $logData);
        }

        return $response;
    }
}
