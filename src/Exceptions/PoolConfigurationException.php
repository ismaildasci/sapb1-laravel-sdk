<?php

declare(strict_types=1);

namespace SapB1\Exceptions;

/**
 * Exception thrown when pool configuration is invalid.
 */
class PoolConfigurationException extends SapB1Exception
{
    public function __construct(
        string $message = 'Invalid pool configuration',
        ?int $errorCode = 500
    ) {
        parent::__construct(
            message: $message,
            errorCode: $errorCode,
            context: ['type' => 'pool_configuration']
        );
    }

    /**
     * Create for invalid value.
     */
    public static function invalidValue(string $key, mixed $value, string $reason): self
    {
        $valueStr = is_scalar($value) ? (string) $value : gettype($value);

        return new self("Invalid value for '{$key}': {$valueStr}. {$reason}");
    }

    /**
     * Create for missing required value.
     */
    public static function missingRequired(string $key): self
    {
        return new self("Missing required configuration: '{$key}'");
    }
}
