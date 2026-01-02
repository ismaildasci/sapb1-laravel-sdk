<?php

declare(strict_types=1);

namespace SapB1\Exceptions;

class SessionExpiredException extends SapB1Exception
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'SAP B1 session has expired',
        ?int $errorCode = null,
        array $context = []
    ) {
        parent::__construct($message, $errorCode, $context);
    }
}
