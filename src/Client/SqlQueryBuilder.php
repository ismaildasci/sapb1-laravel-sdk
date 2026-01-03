<?php

declare(strict_types=1);

namespace SapB1\Client;

use SapB1\Exceptions\SqlQueryException;

class SqlQueryBuilder
{
    protected SapB1Client $client;

    protected string $queryName;

    /**
     * @var array<string, mixed>
     */
    protected array $parameters = [];

    protected ?int $top = null;

    protected ?int $skip = null;

    /**
     * Create a new SqlQueryBuilder instance.
     */
    public function __construct(SapB1Client $client, string $queryName)
    {
        $this->client = $client;
        $this->queryName = $queryName;
    }

    /**
     * Set a query parameter.
     */
    public function param(string $name, mixed $value): self
    {
        $this->parameters[$name] = $value;

        return $this;
    }

    /**
     * Set multiple parameters at once.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function params(array $parameters): self
    {
        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }

    /**
     * Set the top (limit) value.
     */
    public function top(int $value): self
    {
        $this->top = $value;

        return $this;
    }

    /**
     * Alias for top.
     */
    public function limit(int $value): self
    {
        return $this->top($value);
    }

    /**
     * Set the skip (offset) value.
     */
    public function skip(int $value): self
    {
        $this->skip = $value;

        return $this;
    }

    /**
     * Alias for skip.
     */
    public function offset(int $value): self
    {
        return $this->skip($value);
    }

    /**
     * Execute the SQL query.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws SqlQueryException
     */
    public function execute(): array
    {
        $endpoint = $this->buildEndpoint();

        $response = $this->client->get($endpoint);

        if (! $response->successful()) {
            throw new SqlQueryException(
                "SQL query failed: {$this->queryName}",
                ['query' => $this->queryName, 'parameters' => $this->parameters]
            );
        }

        return $response->value();
    }

    /**
     * Execute and get the first result.
     *
     * @return array<string, mixed>|null
     *
     * @throws SqlQueryException
     */
    public function first(): ?array
    {
        $this->top = 1;
        $results = $this->execute();

        return $results[0] ?? null;
    }

    /**
     * Execute and get the count.
     *
     * @throws SqlQueryException
     */
    public function count(): int
    {
        $endpoint = $this->buildEndpoint().'/$count';

        $response = $this->client->get($endpoint);

        if (! $response->successful()) {
            throw new SqlQueryException(
                "SQL query count failed: {$this->queryName}",
                ['query' => $this->queryName]
            );
        }

        return (int) $response->body();
    }

    /**
     * Build the query endpoint.
     */
    protected function buildEndpoint(): string
    {
        // SAP B1 SQLQueries endpoint format
        $endpoint = "SQLQueries('{$this->queryName}')/List";

        $params = [];

        // Add parameters
        foreach ($this->parameters as $name => $value) {
            $formattedValue = $this->formatValue($value);
            $params[] = "{$name}={$formattedValue}";
        }

        // Add pagination
        if ($this->top !== null) {
            $params[] = "\$top={$this->top}";
        }

        if ($this->skip !== null) {
            $params[] = "\$skip={$this->skip}";
        }

        if (! empty($params)) {
            $endpoint .= '?'.implode('&', $params);
        }

        return $endpoint;
    }

    /**
     * Format a parameter value.
     */
    protected function formatValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return "'".$value->format('Y-m-d')."'";
        }

        // String values need to be quoted
        return "'".str_replace("'", "''", (string) $value)."'";
    }

    /**
     * Get the query name.
     */
    public function getQueryName(): string
    {
        return $this->queryName;
    }

    /**
     * Get the parameters.
     *
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
