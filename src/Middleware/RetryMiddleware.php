<?php

declare(strict_types=1);

namespace SapB1\Middleware;

use Closure;
use SapB1\Client\PendingRequest;
use SapB1\Client\Response;
use SapB1\Contracts\MiddlewareInterface;
use SapB1\Exceptions\ConnectionException;

class RetryMiddleware implements MiddlewareInterface
{
    /** @var array<int, int> */
    protected array $retryableStatuses = [429, 500, 502, 503, 504];

    public function __construct(
        protected int $maxAttempts = 3,
        protected int $delayMs = 1000,
        protected bool $exponentialBackoff = true
    ) {}

    public function handle(PendingRequest $request, Closure $next): Response
    {
        $attempts = 0;
        $lastException = null;
        $response = null;

        while ($attempts < $this->maxAttempts) {
            $attempts++;

            try {
                $response = $next($request);

                if ($this->shouldRetry($response, $attempts)) {
                    $this->sleep($attempts, $response);

                    continue;
                }

                return $response;
            } catch (ConnectionException $e) {
                $lastException = $e;

                if ($attempts >= $this->maxAttempts) {
                    throw $e;
                }

                $this->sleep($attempts);
            }
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        // This should never happen, but satisfy static analysis
        if ($response === null) {
            throw new ConnectionException('No response received after all attempts');
        }

        return $response;
    }

    protected function shouldRetry(Response $response, int $attempts): bool
    {
        if ($attempts >= $this->maxAttempts) {
            return false;
        }

        return in_array($response->status(), $this->retryableStatuses, true);
    }

    protected function sleep(int $attempt, ?Response $response = null): void
    {
        $delay = $this->delayMs;

        // Check Retry-After header
        if ($response) {
            $retryAfter = $response->header('Retry-After');
            if ($retryAfter && is_numeric($retryAfter)) {
                $delay = (int) $retryAfter * 1000;
            }
        }

        // Apply exponential backoff
        if ($this->exponentialBackoff) {
            $delay = $delay * (2 ** ($attempt - 1));
        }

        // Add jitter (10%)
        $jitter = (int) ($delay * 0.1 * (mt_rand() / mt_getrandmax()));
        $delay += $jitter;

        usleep($delay * 1000);
    }
}
