<?php

declare(strict_types=1);

namespace SapB1\Exceptions;

class AuthenticationException extends SapB1Exception
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'Authentication failed with SAP B1 Service Layer',
        ?int $errorCode = null,
        array $context = []
    ) {
        parent::__construct($message, $errorCode, $context);
    }
}
