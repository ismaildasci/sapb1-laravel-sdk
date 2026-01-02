<?php

declare(strict_types=1);

namespace SapB1\Testing;

use SapB1\Client\ODataBuilder;
use SapB1\Client\Response;

class FakeSapB1Client
{
    protected string $connection = 'default';

    protected ?ODataBuilder $odata = null;

    public function __construct(
        protected FakeSapB1 $fake
    ) {}

    /**
     * Use a specific connection.
     */
    public function connection(string $connection): self
    {
        $clone = clone $this;
        $clone->connection = $connection;

        return $clone;
    }

    /**
     * Start building an OData query.
     */
    public function query(): ODataBuilder
    {
        $this->odata = ODataBuilder::make();

        return $this->odata;
    }

    /**
     * Set the OData query builder.
     */
    public function withOData(ODataBuilder $odata): self
    {
        $this->odata = $odata;

        return $this;
    }

    /**
     * Get all records from an endpoint.
     */
    public function get(string $endpoint): Response
    {
        $endpoint = $this->appendOData($endpoint);
        $this->fake->record('GET', $endpoint);

        return $this->fake->getResponse('GET', $endpoint);
    }

    /**
     * Find a single record by key.
     */
    public function find(string $endpoint, mixed $key): Response
    {
        $formattedKey = $this->formatKey($key);
        $fullEndpoint = "{$endpoint}({$formattedKey})";
        $this->fake->record('GET', $fullEndpoint);

        return $this->fake->getResponse('GET', $fullEndpoint);
    }

    /**
     * Create a new record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(string $endpoint, array $data): Response
    {
        $this->fake->record('POST', $endpoint, $data);

        return $this->fake->getResponse('POST', $endpoint);
    }

    /**
     * Update an existing record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $endpoint, mixed $key, array $data): Response
    {
        $formattedKey = $this->formatKey($key);
        $fullEndpoint = "{$endpoint}({$formattedKey})";
        $this->fake->record('PATCH', $fullEndpoint, $data);

        return $this->fake->getResponse('PATCH', $fullEndpoint);
    }

    /**
     * Delete a record.
     */
    public function delete(string $endpoint, mixed $key): Response
    {
        $formattedKey = $this->formatKey($key);
        $fullEndpoint = "{$endpoint}({$formattedKey})";
        $this->fake->record('DELETE', $fullEndpoint);

        return $this->fake->getResponse('DELETE', $fullEndpoint);
    }

    /**
     * Call a SAP B1 action.
     *
     * @param  array<string, mixed>  $params
     */
    public function action(string $endpoint, mixed $key, string $action, array $params = []): Response
    {
        $formattedKey = $this->formatKey($key);
        $fullEndpoint = "{$endpoint}({$formattedKey})/{$action}";
        $this->fake->record('POST', $fullEndpoint, $params);

        return $this->fake->getResponse('POST', $fullEndpoint);
    }

    /**
     * Get the next page of results.
     */
    public function nextPage(Response $response): ?Response
    {
        $nextLink = $response->nextLink();

        if ($nextLink === null) {
            return null;
        }

        $path = parse_url($nextLink, PHP_URL_PATH);

        if ($path === false || $path === null) {
            return null;
        }

        $endpoint = basename($path);
        $this->fake->record('GET', $endpoint);

        return $this->fake->getResponse('GET', $endpoint);
    }

    /**
     * Iterate through all pages of results.
     *
     * @return \Generator<int, Response>
     */
    public function paginate(string $endpoint): \Generator
    {
        $response = $this->get($endpoint);
        yield $response;

        while ($response->hasNextPage()) {
            $response = $this->nextPage($response);

            if ($response === null) {
                break;
            }

            yield $response;
        }
    }

    /**
     * Get the count of records.
     */
    public function count(string $endpoint): int
    {
        $response = $this->get($endpoint);

        return $response->count() ?? count($response->value());
    }

    /**
     * Check if a record exists.
     */
    public function exists(string $endpoint, mixed $key): bool
    {
        $response = $this->find($endpoint, $key);

        return $response->successful();
    }

    /**
     * Raw POST request.
     *
     * @param  array<string, mixed>  $data
     */
    public function post(string $endpoint, array $data = []): Response
    {
        $this->fake->record('POST', $endpoint, $data);

        return $this->fake->getResponse('POST', $endpoint);
    }

    /**
     * Raw PUT request.
     *
     * @param  array<string, mixed>  $data
     */
    public function put(string $endpoint, array $data = []): Response
    {
        $this->fake->record('PUT', $endpoint, $data);

        return $this->fake->getResponse('PUT', $endpoint);
    }

    /**
     * Raw PATCH request.
     *
     * @param  array<string, mixed>  $data
     */
    public function patch(string $endpoint, array $data = []): Response
    {
        $this->fake->record('PATCH', $endpoint, $data);

        return $this->fake->getResponse('PATCH', $endpoint);
    }

    /**
     * Raw DELETE request.
     */
    public function rawDelete(string $endpoint): Response
    {
        $this->fake->record('DELETE', $endpoint);

        return $this->fake->getResponse('DELETE', $endpoint);
    }

    /**
     * Logout (no-op in fake).
     */
    public function logout(): void
    {
        // No-op
    }

    /**
     * Refresh session (no-op in fake).
     */
    public function refreshSession(): void
    {
        // No-op
    }

    /**
     * Check if session is valid (always true in fake).
     */
    public function hasValidSession(): bool
    {
        return true;
    }

    /**
     * Get the current connection name.
     */
    public function getConnection(): string
    {
        return $this->connection;
    }

    /**
     * Append OData query to endpoint.
     */
    protected function appendOData(string $endpoint): string
    {
        if ($this->odata !== null) {
            $query = $this->odata->build();
            $this->odata = null;

            return $endpoint.$query;
        }

        return $endpoint;
    }

    /**
     * Format a key for use in endpoint URLs.
     */
    protected function formatKey(mixed $key): string
    {
        if (is_int($key)) {
            return (string) $key;
        }

        if (is_array($key)) {
            $parts = [];
            foreach ($key as $field => $value) {
                $parts[] = "{$field}=".$this->formatSingleKey($value);
            }

            return implode(',', $parts);
        }

        return $this->formatSingleKey($key);
    }

    /**
     * Format a single key value.
     */
    protected function formatSingleKey(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return "'".str_replace("'", "''", $value)."'";
        }

        return (string) $value;
    }
}
