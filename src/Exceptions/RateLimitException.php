<?php

declare(strict_types=1);

namespace SapB1\Exceptions;

/**
 * Exception thrown when rate limit (429 Too Many Requests) is exceeded.
 */
class RateLimitException extends ServiceLayerException
{
    /**
     * The number of seconds to wait before retrying.
     */
    public readonly ?int $retryAfter;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'Rate limit exceeded',
        ?int $retryAfter = null,
        array $context = []
    ) {
        $this->retryAfter = $retryAfter;

        parent::__construct(
            message: $message,
            statusCode: 429,
            sapCode: null,
            context: array_merge($context, [
                'retry_after' => $retryAfter,
            ])
        );
    }

    /**
     * Get the recommended wait time in seconds.
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
