<?php

declare(strict_types=1);

namespace SapB1\Exceptions;

/**
 * Exception thrown for proxy errors (502 Bad Gateway, 504 Gateway Timeout).
 */
class ProxyException extends ServiceLayerException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'Proxy error',
        int $statusCode = 502,
        array $context = []
    ) {
        parent::__construct(
            message: $message,
            statusCode: $statusCode,
            sapCode: null,
            context: $context
        );
    }

    /**
     * Check if this is a Bad Gateway error.
     */
    public function isBadGateway(): bool
    {
        return $this->getStatusCode() === 502;
    }

    /**
     * Check if this is a Gateway Timeout error.
     */
    public function isGatewayTimeout(): bool
    {
        return $this->getStatusCode() === 504;
    }
}
