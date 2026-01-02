<?php

declare(strict_types=1);

namespace SapB1\Exceptions;

use Exception;
use Throwable;

class SapB1Exception extends Exception
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'An error occurred with SAP B1 Service Layer',
        public readonly ?int $errorCode = null,
        public readonly array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $errorCode ?? 0, $previous);
    }

    /**
     * Create exception from SAP B1 API response.
     *
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(array $response): self
    {
        $error = $response['error'] ?? [];
        $message = $error['message']['value'] ?? 'Unknown SAP B1 error';
        $code = $error['code'] ?? null;

        return new self(
            message: $message,
            errorCode: is_numeric($code) ? (int) $code : null,
            context: $response
        );
    }

    /**
     * Convert exception to array for logging/debugging.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'context' => $this->context,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }
}
