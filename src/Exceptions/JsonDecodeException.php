<?php

declare(strict_types=1);

namespace SapB1\Exceptions;

class JsonDecodeException extends SapB1Exception
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message = 'Failed to decode JSON response',
        ?int $jsonError = null,
        array $context = []
    ) {
        parent::__construct(
            message: $message,
            errorCode: $jsonError,
            context: array_merge($context, [
                'json_error' => $jsonError,
                'json_error_message' => $jsonError !== null ? json_last_error_msg() : null,
            ])
        );
    }

    /**
     * Create from current JSON error state.
     */
    public static function fromLastError(string $body = ''): self
    {
        return new self(
            message: 'Failed to decode JSON: '.json_last_error_msg(),
            jsonError: json_last_error(),
            context: [
                'body_preview' => mb_substr($body, 0, 500),
                'body_length' => strlen($body),
            ]
        );
    }

    /**
     * Get the JSON error code.
     */
    public function getJsonError(): ?int
    {
        return $this->context['json_error'] ?? null;
    }
}
