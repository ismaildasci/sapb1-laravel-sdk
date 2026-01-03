<?php

declare(strict_types=1);

namespace SapB1\Profiling;

use Illuminate\Support\Facades\Log;

class QueryProfiler
{
    protected bool $enabled;

    protected int $slowQueryThreshold;

    protected string $store;

    /**
     * @var array<int, array{connection: string, method: string, endpoint: string, duration: float, timestamp: float, slow: bool}>
     */
    protected array $queries = [];

    /**
     * @var array<string, float>
     */
    protected array $activeQueries = [];

    /**
     * Create a new QueryProfiler instance.
     */
    public function __construct()
    {
        $this->enabled = (bool) config('sap-b1.profiling.enabled', false);
        $this->slowQueryThreshold = (int) config('sap-b1.profiling.slow_query_threshold', 1000);
        $this->store = (string) config('sap-b1.profiling.store', 'log');
    }

    /**
     * Check if profiling is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable profiling.
     */
    public function enable(): self
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * Disable profiling.
     */
    public function disable(): self
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * Start timing a query.
     */
    public function start(string $connection, string $method, string $endpoint): string
    {
        $id = uniqid('query_', true);

        if ($this->enabled) {
            $this->activeQueries[$id] = microtime(true);
        }

        return $id;
    }

    /**
     * End timing a query and record it.
     */
    public function end(string $id, string $connection, string $method, string $endpoint): void
    {
        if (! $this->enabled || ! isset($this->activeQueries[$id])) {
            return;
        }

        $startTime = $this->activeQueries[$id];
        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        unset($this->activeQueries[$id]);

        $isSlow = $duration >= $this->slowQueryThreshold;

        $query = [
            'connection' => $connection,
            'method' => $method,
            'endpoint' => $endpoint,
            'duration' => round($duration, 2),
            'timestamp' => $startTime,
            'slow' => $isSlow,
        ];

        $this->queries[] = $query;

        // Store the query
        $this->store($query);

        // Log slow queries
        if ($isSlow) {
            $this->logSlowQuery($query);
        }
    }

    /**
     * Store a profiled query.
     *
     * @param  array{connection: string, method: string, endpoint: string, duration: float, timestamp: float, slow: bool}  $query
     */
    protected function store(array $query): void
    {
        switch ($this->store) {
            case 'log':
                Log::debug('SAP B1 Query Profile', $query);
                break;

            case 'database':
                // Could be implemented to store in a database table
                break;

            case 'redis':
                // Could be implemented to store in Redis
                break;
        }
    }

    /**
     * Log a slow query.
     *
     * @param  array{connection: string, method: string, endpoint: string, duration: float, timestamp: float, slow: bool}  $query
     */
    protected function logSlowQuery(array $query): void
    {
        Log::warning('SAP B1 Slow Query', [
            'connection' => $query['connection'],
            'method' => $query['method'],
            'endpoint' => $query['endpoint'],
            'duration_ms' => $query['duration'],
            'threshold_ms' => $this->slowQueryThreshold,
        ]);
    }

    /**
     * Get all recorded queries.
     *
     * @return array<int, array{connection: string, method: string, endpoint: string, duration: float, timestamp: float, slow: bool}>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Get only slow queries.
     *
     * @return array<int, array{connection: string, method: string, endpoint: string, duration: float, timestamp: float, slow: bool}>
     */
    public function getSlowQueries(): array
    {
        return array_filter($this->queries, fn (array $q): bool => $q['slow']);
    }

    /**
     * Get query statistics.
     *
     * @return array{total: int, slow: int, average_duration: float, max_duration: float, min_duration: float}
     */
    public function getStats(): array
    {
        $total = count($this->queries);

        if ($total === 0) {
            return [
                'total' => 0,
                'slow' => 0,
                'average_duration' => 0.0,
                'max_duration' => 0.0,
                'min_duration' => 0.0,
            ];
        }

        /** @var list<float> $durations */
        $durations = array_column($this->queries, 'duration');
        $slow = count(array_filter($this->queries, fn (array $q): bool => $q['slow']));

        return [
            'total' => $total,
            'slow' => $slow,
            'average_duration' => round(array_sum($durations) / $total, 2),
            'max_duration' => $durations !== [] ? max($durations) : 0.0,
            'min_duration' => $durations !== [] ? min($durations) : 0.0,
        ];
    }

    /**
     * Clear recorded queries.
     */
    public function clear(): self
    {
        $this->queries = [];
        $this->activeQueries = [];

        return $this;
    }

    /**
     * Set the slow query threshold.
     */
    public function setSlowQueryThreshold(int $milliseconds): self
    {
        $this->slowQueryThreshold = $milliseconds;

        return $this;
    }

    /**
     * Get the slow query threshold.
     */
    public function getSlowQueryThreshold(): int
    {
        return $this->slowQueryThreshold;
    }

    /**
     * Get query count by endpoint.
     *
     * @return array<string, int>
     */
    public function getQueryCountByEndpoint(): array
    {
        $counts = [];

        foreach ($this->queries as $query) {
            $endpoint = $query['endpoint'];
            $counts[$endpoint] = ($counts[$endpoint] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }

    /**
     * Get average duration by endpoint.
     *
     * @return array<string, float>
     */
    public function getAverageDurationByEndpoint(): array
    {
        $totals = [];
        $counts = [];

        foreach ($this->queries as $query) {
            $endpoint = $query['endpoint'];
            $totals[$endpoint] = ($totals[$endpoint] ?? 0) + $query['duration'];
            $counts[$endpoint] = ($counts[$endpoint] ?? 0) + 1;
        }

        $averages = [];
        foreach ($totals as $endpoint => $total) {
            $averages[$endpoint] = round($total / $counts[$endpoint], 2);
        }

        arsort($averages);

        return $averages;
    }
}
