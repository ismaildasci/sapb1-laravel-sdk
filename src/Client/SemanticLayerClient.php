<?php

declare(strict_types=1);

namespace SapB1\Client;

use SapB1\Exceptions\ServiceLayerException;

class SemanticLayerClient
{
    protected SapB1Client $client;

    protected string $queryName;

    /**
     * @var array<string, mixed>
     */
    protected array $parameters = [];

    /**
     * @var array<int, string>
     */
    protected array $dimensions = [];

    /**
     * @var array<int, string>
     */
    protected array $measures = [];

    /**
     * @var array<int, string>
     */
    protected array $filters = [];

    /**
     * @var array<string, string>
     */
    protected array $orderBy = [];

    protected ?int $top = null;

    protected ?int $skip = null;

    /**
     * Create a new SemanticLayerClient instance.
     */
    public function __construct(SapB1Client $client, string $queryName)
    {
        $this->client = $client;
        $this->queryName = $queryName;
    }

    /**
     * Add dimensions to the query.
     *
     * @param  array<int, string>|string  $dimensions
     */
    public function dimensions(array|string $dimensions): self
    {
        $dimensions = is_array($dimensions) ? $dimensions : func_get_args();

        $this->dimensions = array_merge($this->dimensions, $dimensions);

        return $this;
    }

    /**
     * Add measures to the query.
     *
     * @param  array<int, string>|string  $measures
     */
    public function measures(array|string $measures): self
    {
        $measures = is_array($measures) ? $measures : func_get_args();

        $this->measures = array_merge($this->measures, $measures);

        return $this;
    }

    /**
     * Add a filter condition.
     */
    public function filter(string $dimension, string $operator, mixed $value): self
    {
        $formattedValue = $this->formatValue($value);
        $this->filters[] = "{$dimension} {$operator} {$formattedValue}";

        return $this;
    }

    /**
     * Add a where equals condition.
     */
    public function where(string $dimension, mixed $value): self
    {
        return $this->filter($dimension, 'eq', $value);
    }

    /**
     * Add a where between condition.
     */
    public function whereBetween(string $dimension, mixed $start, mixed $end): self
    {
        $formattedStart = $this->formatValue($start);
        $formattedEnd = $this->formatValue($end);
        $this->filters[] = "({$dimension} ge {$formattedStart} and {$dimension} le {$formattedEnd})";

        return $this;
    }

    /**
     * Add a where in condition.
     *
     * @param  array<int, mixed>  $values
     */
    public function whereIn(string $dimension, array $values): self
    {
        $conditions = array_map(
            fn (mixed $value): string => "{$dimension} eq ".$this->formatValue($value),
            $values
        );

        $this->filters[] = '('.implode(' or ', $conditions).')';

        return $this;
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
     * Add order by clause.
     */
    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $this->orderBy[$field] = $direction;

        return $this;
    }

    /**
     * Add order by descending clause.
     */
    public function orderByDesc(string $field): self
    {
        return $this->orderBy($field, 'desc');
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
     * Execute the semantic layer query.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ServiceLayerException
     */
    public function execute(): array
    {
        $endpoint = $this->buildEndpoint();

        $response = $this->client->get($endpoint);

        if (! $response->successful()) {
            throw new ServiceLayerException(
                message: "Semantic layer query failed: {$this->queryName}",
                statusCode: $response->status(),
                context: ['query' => $this->queryName]
            );
        }

        return $response->value();
    }

    /**
     * Execute and get the first result.
     *
     * @return array<string, mixed>|null
     *
     * @throws ServiceLayerException
     */
    public function first(): ?array
    {
        $this->top = 1;
        $results = $this->execute();

        return $results[0] ?? null;
    }

    /**
     * Execute and get aggregated results.
     *
     * @return array<string, mixed>
     *
     * @throws ServiceLayerException
     */
    public function aggregate(): array
    {
        $endpoint = $this->buildEndpoint();

        $response = $this->client->get($endpoint);

        if (! $response->successful()) {
            throw new ServiceLayerException(
                message: "Semantic layer aggregation failed: {$this->queryName}",
                statusCode: $response->status(),
                context: ['query' => $this->queryName]
            );
        }

        $data = $response->value();

        // Return first row for aggregations
        return $data[0] ?? [];
    }

    /**
     * Build the query endpoint.
     */
    protected function buildEndpoint(): string
    {
        // SAP B1 Semantic Layer endpoint format
        $endpoint = "sml.svc/{$this->queryName}";

        $params = [];

        // Add dimensions as $select
        if (! empty($this->dimensions) || ! empty($this->measures)) {
            $selectFields = array_merge($this->dimensions, $this->measures);
            $params[] = '$select='.implode(',', $selectFields);
        }

        // Add filters
        if (! empty($this->filters)) {
            $params[] = '$filter='.implode(' and ', $this->filters);
        }

        // Add parameters
        foreach ($this->parameters as $name => $value) {
            $formattedValue = $this->formatValue($value);
            $params[] = "{$name}={$formattedValue}";
        }

        // Add order by
        if (! empty($this->orderBy)) {
            $orderParts = [];
            foreach ($this->orderBy as $field => $direction) {
                $orderParts[] = "{$field} {$direction}";
            }
            $params[] = '$orderby='.implode(',', $orderParts);
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
            return "datetime'".$value->format('Y-m-d\TH:i:s')."'";
        }

        // String values need to be quoted
        return "'".str_replace("'", "''", (string) $value)."'";
    }

    /**
     * Get available queries from the semantic layer.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ServiceLayerException
     */
    public static function getAvailableQueries(SapB1Client $client): array
    {
        $response = $client->get('sml.svc');

        if (! $response->successful()) {
            throw new ServiceLayerException(
                message: 'Failed to retrieve semantic layer queries',
                statusCode: $response->status()
            );
        }

        return $response->value();
    }

    /**
     * Get the query name.
     */
    public function getQueryName(): string
    {
        return $this->queryName;
    }

    /**
     * Get the current dimensions.
     *
     * @return array<int, string>
     */
    public function getDimensions(): array
    {
        return $this->dimensions;
    }

    /**
     * Get the current measures.
     *
     * @return array<int, string>
     */
    public function getMeasures(): array
    {
        return $this->measures;
    }

    /**
     * Get the current filters.
     *
     * @return array<int, string>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }
}
