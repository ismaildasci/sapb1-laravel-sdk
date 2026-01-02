<?php

declare(strict_types=1);

namespace SapB1\Client;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, string>
 */
class ODataBuilder implements Arrayable
{
    /**
     * @var array<int, string>
     */
    protected array $select = [];

    /**
     * @var array<int, string>
     */
    protected array $filters = [];

    /**
     * @var array<int, string>
     */
    protected array $orderBy = [];

    /**
     * @var array<int, string>
     */
    protected array $expand = [];

    protected ?int $top = null;

    protected ?int $skip = null;

    protected bool $inlineCount = false;

    protected ?string $crossCompany = null;

    /**
     * Create a new ODataBuilder instance.
     */
    public static function make(): self
    {
        return new self;
    }

    /**
     * Add select fields.
     *
     * @param  array<int, string>|string  $fields
     */
    public function select(array|string $fields): self
    {
        $fields = is_array($fields) ? $fields : func_get_args();

        $this->select = array_merge($this->select, $fields);

        return $this;
    }

    /**
     * Add a filter condition.
     */
    public function filter(string $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * Add a where condition (alias for filter with eq operator).
     */
    public function where(string $field, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = 'eq';
        }

        $formattedValue = $this->formatValue($value);

        return $this->filter("{$field} {$operator} {$formattedValue}");
    }

    /**
     * Add an or where condition.
     */
    public function orWhere(string $field, mixed $operator, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = 'eq';
        }

        $formattedValue = $this->formatValue($value);

        if (count($this->filters) > 0) {
            $lastFilter = array_pop($this->filters);
            $this->filters[] = "({$lastFilter} or {$field} {$operator} {$formattedValue})";
        } else {
            $this->filters[] = "{$field} {$operator} {$formattedValue}";
        }

        return $this;
    }

    /**
     * Add a where in condition.
     *
     * @param  array<int, mixed>  $values
     */
    public function whereIn(string $field, array $values): self
    {
        $conditions = array_map(
            fn (mixed $value): string => "{$field} eq ".$this->formatValue($value),
            $values
        );

        return $this->filter('('.implode(' or ', $conditions).')');
    }

    /**
     * Add a where not equal condition.
     */
    public function whereNot(string $field, mixed $value): self
    {
        return $this->where($field, 'ne', $value);
    }

    /**
     * Add a where greater than condition.
     */
    public function whereGreaterThan(string $field, mixed $value): self
    {
        return $this->where($field, 'gt', $value);
    }

    /**
     * Add a where greater than or equal condition.
     */
    public function whereGreaterThanOrEqual(string $field, mixed $value): self
    {
        return $this->where($field, 'ge', $value);
    }

    /**
     * Add a where less than condition.
     */
    public function whereLessThan(string $field, mixed $value): self
    {
        return $this->where($field, 'lt', $value);
    }

    /**
     * Add a where less than or equal condition.
     */
    public function whereLessThanOrEqual(string $field, mixed $value): self
    {
        return $this->where($field, 'le', $value);
    }

    /**
     * Add a contains filter (like %value%).
     */
    public function whereContains(string $field, string $value): self
    {
        return $this->filter("contains({$field}, '{$this->escapeString($value)}')");
    }

    /**
     * Add a starts with filter.
     */
    public function whereStartsWith(string $field, string $value): self
    {
        return $this->filter("startswith({$field}, '{$this->escapeString($value)}')");
    }

    /**
     * Add an ends with filter.
     */
    public function whereEndsWith(string $field, string $value): self
    {
        return $this->filter("endswith({$field}, '{$this->escapeString($value)}')");
    }

    /**
     * Add a where null condition.
     */
    public function whereNull(string $field): self
    {
        return $this->filter("{$field} eq null");
    }

    /**
     * Add a where not null condition.
     */
    public function whereNotNull(string $field): self
    {
        return $this->filter("{$field} ne null");
    }

    /**
     * Add a where between condition.
     */
    public function whereBetween(string $field, mixed $start, mixed $end): self
    {
        $formattedStart = $this->formatValue($start);
        $formattedEnd = $this->formatValue($end);

        return $this->filter("({$field} ge {$formattedStart} and {$field} le {$formattedEnd})");
    }

    /**
     * Add order by clause.
     */
    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $this->orderBy[] = "{$field} {$direction}";

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
     * Paginate results.
     */
    public function page(int $page, int $perPage = 20): self
    {
        $this->top = $perPage;
        $this->skip = ($page - 1) * $perPage;

        return $this;
    }

    /**
     * Add expand clause for related entities.
     *
     * @param  array<int, string>|string  $relations
     */
    public function expand(array|string $relations): self
    {
        $relations = is_array($relations) ? $relations : func_get_args();

        $this->expand = array_merge($this->expand, $relations);

        return $this;
    }

    /**
     * Enable inline count.
     */
    public function inlineCount(bool $enabled = true): self
    {
        $this->inlineCount = $enabled;

        return $this;
    }

    /**
     * Alias for inlineCount.
     */
    public function withCount(): self
    {
        return $this->inlineCount(true);
    }

    /**
     * Set cross company query parameter.
     */
    public function crossCompany(?string $company = null): self
    {
        $this->crossCompany = $company ?? '*';

        return $this;
    }

    /**
     * Build the query string.
     */
    public function build(): string
    {
        $params = $this->toArray();

        if (empty($params)) {
            return '';
        }

        return '?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Get the query parameters as an array.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        $params = [];

        if (! empty($this->select)) {
            $params['$select'] = implode(',', array_unique($this->select));
        }

        if (! empty($this->filters)) {
            $params['$filter'] = implode(' and ', $this->filters);
        }

        if (! empty($this->orderBy)) {
            $params['$orderby'] = implode(',', $this->orderBy);
        }

        if (! empty($this->expand)) {
            $params['$expand'] = implode(',', array_unique($this->expand));
        }

        if ($this->top !== null) {
            $params['$top'] = (string) $this->top;
        }

        if ($this->skip !== null) {
            $params['$skip'] = (string) $this->skip;
        }

        if ($this->inlineCount) {
            $params['$inlinecount'] = 'allpages';
        }

        if ($this->crossCompany !== null) {
            $params['$crosscompany'] = $this->crossCompany;
        }

        return $params;
    }

    /**
     * Format a value for OData query.
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
            return "'".$value->format('Y-m-d\TH:i:s')."'";
        }

        return "'".$this->escapeString((string) $value)."'";
    }

    /**
     * Escape a string value for OData query.
     */
    protected function escapeString(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    /**
     * Reset all query parameters.
     */
    public function reset(): self
    {
        $this->select = [];
        $this->filters = [];
        $this->orderBy = [];
        $this->expand = [];
        $this->top = null;
        $this->skip = null;
        $this->inlineCount = false;
        $this->crossCompany = null;

        return $this;
    }

    /**
     * Clone the builder.
     */
    public function clone(): self
    {
        return clone $this;
    }

    /**
     * Get the query string representation.
     */
    public function __toString(): string
    {
        return $this->build();
    }
}
