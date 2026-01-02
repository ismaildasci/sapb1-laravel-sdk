<?php

declare(strict_types=1);

namespace SapB1\Exceptions;

class ServiceLayerException extends SapB1Exception
{
    protected int $statusCode = 0;

    protected ?string $sapCode = null;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'SAP B1 Service Layer returned an error',
        int $statusCode = 0,
        ?string $sapCode = null,
        array $context = []
    ) {
        $this->statusCode = $statusCode;
        $this->sapCode = $sapCode;

        parent::__construct($message, $statusCode, $context);
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the SAP error code.
     */
    public function getSapCode(): ?string
    {
        return $this->sapCode;
    }

    /**
     * Create exception from SAP B1 Service Layer error response.
     *
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(array $response, int $statusCode = 0): self
    {
        $error = $response['error'] ?? [];

        // SAP B1 Service Layer error format
        $message = $error['message']['value']
            ?? $error['message']
            ?? 'Unknown Service Layer error';

        /** @var string|null $code */
        $code = $error['code'] ?? null;

        return new self(
            message: is_string($message) ? $message : 'Unknown Service Layer error',
            statusCode: $statusCode,
            sapCode: $code,
            context: [
                'error' => $error,
                'response' => $response,
            ]
        );
    }
}
