<?php

declare(strict_types=1);

namespace SapB1\Client;

/**
 * Fluent wrapper for SAP B1 Service Layer endpoints.
 *
 * Provides a fluent interface for interacting with specific SAP B1 entities
 * such as BusinessPartners, Orders, Items, etc.
 *
 * @example
 * ```php
 * $client->service('BusinessPartners')->find('C001');
 * $client->service('Orders')->create(['CardCode' => 'C001', ...]);
 * $client->service('Items')->queryBuilder()->top(10)->get();
 * ```
 */
class ServiceEndpoint
{
    private ?ODataBuilder $odata = null;

    public function __construct(
        private SapB1Client $client,
        private string $endpoint
    ) {}

    /**
     * Get all records from this endpoint.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $response = $this->applyOData($this->client)->get($this->endpoint);

        return $response->json();
    }

    /**
     * Get records with query parameters.
     *
     * @param  array<string, mixed>  $params  OData query parameters
     * @return array<string, mixed>
     */
    public function get(array $params = []): array
    {
        $query = '';

        if (! empty($params)) {
            $query = '?'.http_build_query($params);
        } elseif ($this->odata !== null) {
            // ODataBuilder::build() already returns a string starting with '?'
            $query = $this->odata->build();
            $this->odata = null;
        }

        $response = $this->client->get($this->endpoint.$query);

        return $response->json();
    }

    /**
     * Find a single record by key.
     *
     * @return array<string, mixed>
     */
    public function find(mixed $key): array
    {
        $response = $this->client->find($this->endpoint, $key);

        return $response->json();
    }

    /**
     * Create a new record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function create(array $data): array
    {
        $response = $this->client->create($this->endpoint, $data);

        return $response->json();
    }

    /**
     * Update an existing record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function update(mixed $key, array $data): array
    {
        $response = $this->client->update($this->endpoint, $key, $data);

        // SAP B1 PATCH returns 204 No Content on success
        return $response->json() ?? [];
    }

    /**
     * Delete a record.
     */
    public function delete(mixed $key): bool
    {
        $response = $this->client->delete($this->endpoint, $key);

        return $response->successful();
    }

    /**
     * Call an action on a record.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function action(mixed $key, string $action, array $params = []): array
    {
        $response = $this->client->action($this->endpoint, $key, $action, $params);

        // SAP B1 actions (Cancel, Close, etc.) return 204 No Content on success
        return $response->json() ?? [];
    }

    /**
     * Check if a record exists.
     */
    public function exists(mixed $key): bool
    {
        return $this->client->exists($this->endpoint, $key);
    }

    /**
     * Get the count of records.
     */
    public function count(): int
    {
        return $this->client->count($this->endpoint);
    }

    /**
     * Start building an OData query for this endpoint.
     */
    public function queryBuilder(): self
    {
        $this->odata = ODataBuilder::make();

        return $this;
    }

    /**
     * Select specific fields.
     *
     * @param  array<string>|string  $fields
     */
    public function select(array|string $fields): self
    {
        $this->ensureOData()->select($fields);

        return $this;
    }

    /**
     * Filter records.
     */
    public function filter(string $filter): self
    {
        $this->ensureOData()->filter($filter);

        return $this;
    }

    /**
     * Where condition.
     */
    public function where(string $field, mixed $operator, mixed $value = null): self
    {
        $this->ensureOData()->where($field, $operator, $value);

        return $this;
    }

    /**
     * Limit the number of records.
     */
    public function top(int $value): self
    {
        $this->ensureOData()->top($value);

        return $this;
    }

    /**
     * Skip a number of records.
     */
    public function skip(int $value): self
    {
        $this->ensureOData()->skip($value);

        return $this;
    }

    /**
     * Order by a field.
     */
    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $this->ensureOData()->orderBy($field, $direction);

        return $this;
    }

    /**
     * Order by descending.
     */
    public function orderByDesc(string $field): self
    {
        $this->ensureOData()->orderByDesc($field);

        return $this;
    }

    /**
     * Expand related entities.
     *
     * @param  array<string>|string  $relations
     */
    public function expand(array|string $relations): self
    {
        $this->ensureOData()->expand($relations);

        return $this;
    }

    /**
     * Include count in response.
     */
    public function withCount(): self
    {
        $this->ensureOData()->withCount();

        return $this;
    }

    /**
     * Ensure OData builder exists.
     */
    private function ensureOData(): ODataBuilder
    {
        if ($this->odata === null) {
            $this->odata = ODataBuilder::make();
        }

        return $this->odata;
    }

    /**
     * Apply OData to client if set.
     */
    private function applyOData(SapB1Client $client): SapB1Client
    {
        if ($this->odata !== null) {
            $client = $client->withOData($this->odata);
            $this->odata = null;
        }

        return $client;
    }
}
