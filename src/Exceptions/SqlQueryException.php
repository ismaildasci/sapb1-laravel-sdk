<?php

declare(strict_types=1);

namespace SapB1\Exceptions;

class SqlQueryException extends SapB1Exception
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'SQL query failed',
        array $context = []
    ) {
        parent::__construct($message, null, $context);
    }
}
