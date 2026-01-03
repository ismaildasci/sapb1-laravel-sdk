<?php

declare(strict_types=1);

namespace SapB1\Exceptions;

/**
 * Exception thrown when circuit breaker is open and blocking requests.
 *
 * This exception indicates that the service has been experiencing too many
 * failures and requests are being blocked to prevent cascading failures.
 */
class CircuitBreakerOpenException extends SapB1Exception
{
    /**
     * The endpoint that triggered the exception.
     */
    public readonly string $endpoint;

    /**
     * The recommended wait time in seconds before retrying.
     */
    public readonly int $retryAfter;

    /**
     * Create a new CircuitBreakerOpenException instance.
     *
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $endpoint = '*',
        int $retryAfter = 30,
        string $message = 'Circuit breaker is open',
        array $context = []
    ) {
        $this->endpoint = $endpoint;
        $this->retryAfter = $retryAfter;

        parent::__construct(
            message: $message,
            errorCode: 503,
            context: array_merge($context, [
                'endpoint' => $endpoint,
                'retry_after' => $retryAfter,
            ])
        );
    }

    /**
     * Get the endpoint that triggered the exception.
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Get the recommended wait time in seconds.
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
