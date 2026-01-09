<?php

declare(strict_types=1);

namespace SapB1\Exceptions;

use SapB1\Errors\ErrorCodeDatabase;

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
     * Get numeric SAP error code (if available).
     */
    public function getNumericCode(): ?int
    {
        if ($this->sapCode === null) {
            return ErrorCodeDatabase::extractCode($this->getMessage());
        }

        return is_numeric($this->sapCode) ? (int) $this->sapCode : null;
    }

    /**
     * Get human-readable message for the error.
     */
    public function getHumanMessage(): string
    {
        $code = $this->getNumericCode() ?? $this->statusCode;
        $humanMessage = ErrorCodeDatabase::getMessage($code);

        return $humanMessage ?? $this->getMessage();
    }

    /**
     * Get suggestion for fixing the error.
     */
    public function getSuggestion(): ?string
    {
        $code = $this->getNumericCode() ?? $this->statusCode;

        return ErrorCodeDatabase::getSuggestion($code);
    }

    /**
     * Get error category.
     */
    public function getCategory(): ?string
    {
        $code = $this->getNumericCode() ?? $this->statusCode;

        return ErrorCodeDatabase::getCategory($code);
    }

    /**
     * Check if error is authentication related.
     */
    public function isAuthError(): bool
    {
        $code = $this->getNumericCode() ?? $this->statusCode;

        return ErrorCodeDatabase::isAuthError($code);
    }

    /**
     * Check if error is retryable.
     */
    public function isRetryable(): bool
    {
        $code = $this->getNumericCode() ?? $this->statusCode;

        return ErrorCodeDatabase::isRetryable($code);
    }

    /**
     * Get documentation link for the error.
     */
    public function getDocLink(): ?string
    {
        $code = $this->getNumericCode();

        if ($code === null) {
            return null;
        }

        return sprintf(
            'https://help.sap.com/docs/SAP_BUSINESS_ONE/search?q=%d',
            abs($code)
        );
    }

    /**
     * Get detailed error information.
     *
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return [
            'message' => $this->getMessage(),
            'human_message' => $this->getHumanMessage(),
            'suggestion' => $this->getSuggestion(),
            'category' => $this->getCategory(),
            'sap_code' => $this->sapCode,
            'status_code' => $this->statusCode,
            'is_retryable' => $this->isRetryable(),
            'doc_link' => $this->getDocLink(),
        ];
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
