<?php

declare(strict_types=1);

namespace SapB1\Exceptions;

class BatchException extends SapB1Exception
{
    protected int $statusCode;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'Batch operation failed',
        int $statusCode = 0,
        array $context = []
    ) {
        $this->statusCode = $statusCode;

        parent::__construct($message, null, $context);
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
