<?php

declare(strict_types=1);

namespace SapB1\Exceptions;

class ServiceLayerException extends SapB1Exception
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'SAP B1 Service Layer returned an error',
        ?int $errorCode = null,
        array $context = []
    ) {
        parent::__construct($message, $errorCode, $context);
    }

    /**
     * Create exception from SAP B1 Service Layer error response.
     *
     * @param  array<string, mixed>  $response
     */
    public static function fromResponse(array $response): self
    {
        $error = $response['error'] ?? [];

        // SAP B1 Service Layer error format
        $message = $error['message']['value']
            ?? $error['message']
            ?? 'Unknown Service Layer error';

        $code = $error['code'] ?? null;

        return new self(
            message: $message,
            errorCode: is_numeric($code) ? (int) $code : null,
            context: [
                'error' => $error,
                'response' => $response,
            ]
        );
    }
}
