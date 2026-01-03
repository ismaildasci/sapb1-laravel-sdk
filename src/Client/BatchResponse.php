<?php

declare(strict_types=1);

namespace SapB1\Client;

use SapB1\Exceptions\BatchException;

class BatchResponse
{
    /**
     * @var array<int, array{status: int, headers: array<string, string>, body: array<string, mixed>|null, success: bool}>
     */
    protected array $responses = [];

    protected bool $hasErrors = false;

    /**
     * Create a new BatchResponse instance.
     */
    public function __construct(string $rawResponse, string $boundary)
    {
        $this->parseResponse($rawResponse, $boundary);
    }

    /**
     * Parse the multipart response.
     */
    protected function parseResponse(string $rawResponse, string $boundary): void
    {
        // Split by boundary
        $parts = preg_split('/--'.preg_quote($boundary, '/').'(--)?\r?\n/', $rawResponse);

        if ($parts === false) {
            return;
        }

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part) || $part === '--') {
                continue;
            }

            // Check if this is a changeset
            if (str_contains($part, 'multipart/mixed')) {
                $this->parseChangeset($part);

                continue;
            }

            // Parse individual response
            $parsedResponse = $this->parseIndividualResponse($part);
            if ($parsedResponse !== null) {
                $this->responses[] = $parsedResponse;
                if (! $parsedResponse['success']) {
                    $this->hasErrors = true;
                }
            }
        }
    }

    /**
     * Parse a changeset response.
     */
    protected function parseChangeset(string $changeset): void
    {
        // Extract changeset boundary
        if (preg_match('/boundary=([^\s;]+)/', $changeset, $matches)) {
            $changesetBoundary = $matches[1];
            $parts = preg_split('/--'.preg_quote($changesetBoundary, '/').'(--)?\r?\n/', $changeset);

            if ($parts === false) {
                return;
            }

            foreach ($parts as $part) {
                $part = trim($part);
                if (empty($part) || $part === '--' || str_contains($part, 'multipart/mixed')) {
                    continue;
                }

                $parsedResponse = $this->parseIndividualResponse($part);
                if ($parsedResponse !== null) {
                    $this->responses[] = $parsedResponse;
                    if (! $parsedResponse['success']) {
                        $this->hasErrors = true;
                    }
                }
            }
        }
    }

    /**
     * Parse an individual HTTP response.
     *
     * @return array{status: int, headers: array<string, string>, body: array<string, mixed>|null, success: bool}|null
     */
    protected function parseIndividualResponse(string $part): ?array
    {
        // Find the HTTP response line
        if (! preg_match('/HTTP\/[\d.]+ (\d+)/', $part, $matches)) {
            return null;
        }

        $statusCode = (int) $matches[1];

        // Extract headers and body
        $headers = [];
        $body = null;

        // Split headers from body (double newline)
        $headerBodySplit = preg_split('/\r?\n\r?\n/', $part, 3);

        if ($headerBodySplit !== false && count($headerBodySplit) >= 2) {
            // Parse headers from the HTTP response part
            $headerSection = $headerBodySplit[1] ?? '';
            $bodySection = $headerBodySplit[2] ?? '';

            // Parse headers
            $headerLines = explode("\n", $headerSection);
            foreach ($headerLines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, 'HTTP/')) {
                    continue;
                }
                if (str_contains($line, ':')) {
                    [$key, $value] = explode(':', $line, 2);
                    $headers[trim($key)] = trim($value);
                }
            }

            // Parse body as JSON
            $bodySection = trim($bodySection);
            if (! empty($bodySection)) {
                $decoded = json_decode($bodySection, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $body = $decoded;
                }
            }
        }

        return [
            'status' => $statusCode,
            'headers' => $headers,
            'body' => $body,
            'success' => $statusCode >= 200 && $statusCode < 300,
        ];
    }

    /**
     * Get all responses.
     *
     * @return array<int, array{status: int, headers: array<string, string>, body: array<string, mixed>|null, success: bool}>
     */
    public function all(): array
    {
        return $this->responses;
    }

    /**
     * Get a specific response by index.
     *
     * @return array{status: int, headers: array<string, string>, body: array<string, mixed>|null, success: bool}|null
     */
    public function get(int $index): ?array
    {
        return $this->responses[$index] ?? null;
    }

    /**
     * Get the first response.
     *
     * @return array{status: int, headers: array<string, string>, body: array<string, mixed>|null, success: bool}|null
     */
    public function first(): ?array
    {
        return $this->responses[0] ?? null;
    }

    /**
     * Get the last response.
     *
     * @return array{status: int, headers: array<string, string>, body: array<string, mixed>|null, success: bool}|null
     */
    public function last(): ?array
    {
        if (empty($this->responses)) {
            return null;
        }

        return $this->responses[count($this->responses) - 1];
    }

    /**
     * Check if all requests were successful.
     */
    public function successful(): bool
    {
        return ! $this->hasErrors && ! empty($this->responses);
    }

    /**
     * Check if any request failed.
     */
    public function hasErrors(): bool
    {
        return $this->hasErrors;
    }

    /**
     * Get only successful responses.
     *
     * @return array<int, array{status: int, headers: array<string, string>, body: array<string, mixed>|null, success: bool}>
     */
    public function successfulResponses(): array
    {
        return array_filter($this->responses, fn (array $r): bool => $r['success']);
    }

    /**
     * Get only failed responses.
     *
     * @return array<int, array{status: int, headers: array<string, string>, body: array<string, mixed>|null, success: bool}>
     */
    public function failedResponses(): array
    {
        return array_filter($this->responses, fn (array $r): bool => ! $r['success']);
    }

    /**
     * Get the count of responses.
     */
    public function count(): int
    {
        return count($this->responses);
    }

    /**
     * Throw an exception if any request failed.
     *
     * @throws BatchException
     */
    public function throw(): self
    {
        if ($this->hasErrors) {
            $failed = $this->failedResponses();
            $first = reset($failed);

            $message = 'Batch request failed';
            if ($first !== false && isset($first['body']['error']['message']['value'])) {
                $message = $first['body']['error']['message']['value'];
            }

            throw new BatchException($message, $first['status'] ?? 0);
        }

        return $this;
    }

    /**
     * Get response bodies as a collection.
     *
     * @return array<int, array<string, mixed>|null>
     */
    public function bodies(): array
    {
        return array_map(fn (array $r): ?array => $r['body'], $this->responses);
    }

    /**
     * Check if empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->responses);
    }
}
