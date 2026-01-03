<?php

declare(strict_types=1);

namespace SapB1\Exceptions;

/**
 * Exception thrown when no session is available from the pool within timeout.
 */
class PoolExhaustedException extends SapB1Exception
{
    public function __construct(
        public readonly string $connection,
        public readonly int $timeout = 30,
        string $message = 'Session pool exhausted',
        ?int $errorCode = 503
    ) {
        parent::__construct(
            message: $message,
            errorCode: $errorCode,
            context: [
                'connection' => $this->connection,
                'timeout' => $this->timeout,
            ]
        );
    }

    /**
     * Create with detailed message.
     */
    public static function forConnection(string $connection, int $timeout): self
    {
        return new self(
            connection: $connection,
            timeout: $timeout,
            message: "No session available in pool for connection '{$connection}' within {$timeout} seconds"
        );
    }

    /**
     * Get the wait timeout used.
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Get the connection that exhausted.
     */
    public function getConnection(): string
    {
        return $this->connection;
    }
}
