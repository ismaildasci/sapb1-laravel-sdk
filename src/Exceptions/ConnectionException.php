<?php

declare(strict_types=1);

namespace SapB1\Exceptions;

class ConnectionException extends SapB1Exception
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'Failed to connect to SAP B1 Service Layer',
        ?int $errorCode = null,
        array $context = []
    ) {
        parent::__construct($message, $errorCode, $context);
    }
}
