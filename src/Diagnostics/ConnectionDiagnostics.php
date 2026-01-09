<?php

declare(strict_types=1);

namespace SapB1\Diagnostics;

use SapB1\Client\SapB1Client;
use SapB1\Health\SapB1HealthCheck;
use SapB1\Profiling\QueryProfiler;
use SapB1\Services\CompanyService;
use SapB1\Session\SessionManager;

class ConnectionDiagnostics
{
    public function __construct(
        protected SapB1Client $client,
        protected SessionManager $sessionManager,
        protected SapB1HealthCheck $healthCheck,
        protected ?QueryProfiler $profiler = null,
        protected string $connection = 'default'
    ) {}

    /**
     * Run full diagnostics report.
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        $startTime = microtime(true);

        $report = [
            'connection' => $this->connection,
            'timestamp' => now()->toIso8601String(),
            'health' => $this->getHealthStatus(),
            'latency' => $this->measureLatency(),
            'session' => $this->getSessionStatus(),
            'service_layer' => $this->getServiceLayerInfo(),
            'company' => $this->getCompanyInfo(),
            'configuration' => $this->getConfiguration(),
        ];

        if ($this->profiler !== null) {
            $report['performance'] = $this->getPerformanceMetrics();
        }

        $report['diagnostics_duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);

        return $report;
    }

    /**
     * Get quick health status.
     *
     * @return array<string, mixed>
     */
    public function getHealthStatus(): array
    {
        $result = $this->healthCheck->check($this->connection);

        return [
            'healthy' => $result->isHealthy(),
            'response_time_ms' => $result->responseTime,
            'message' => $result->message,
            'checked_at' => $result->checkedAt?->format('c') ?? now()->format('c'),
        ];
    }

    /**
     * Measure connection latency.
     *
     * @return array<string, float|int>
     */
    public function measureLatency(int $samples = 5): array
    {
        $times = [];

        for ($i = 0; $i < $samples; $i++) {
            $start = microtime(true);

            try {
                $this->client
                    ->connection($this->connection)
                    ->service('Currencies')
                    ->top(1)
                    ->select('Code')
                    ->get();

                $times[] = (microtime(true) - $start) * 1000;
            } catch (\Throwable) {
                $times[] = -1.0;
            }
        }

        $validTimes = array_filter($times, fn ($t) => $t >= 0);

        if (empty($validTimes)) {
            return [
                'avg_ms' => -1.0,
                'min_ms' => -1.0,
                'max_ms' => -1.0,
                'p95_ms' => -1.0,
                'samples' => $samples,
                'failures' => $samples,
            ];
        }

        sort($validTimes);
        $count = count($validTimes);
        $p95Index = (int) floor($count * 0.95);

        return [
            'avg_ms' => round(array_sum($validTimes) / $count, 2),
            'min_ms' => round(min($validTimes), 2),
            'max_ms' => round(max($validTimes), 2),
            'p95_ms' => round($validTimes[$p95Index] ?? $validTimes[$count - 1], 2),
            'samples' => $samples,
            'failures' => $samples - $count,
        ];
    }

    /**
     * Get session status.
     *
     * @return array<string, mixed>
     */
    public function getSessionStatus(): array
    {
        $hasSession = $this->sessionManager->hasValidSession($this->connection);

        $status = [
            'has_valid_session' => $hasSession,
            'driver' => config('sap-b1.session.driver', 'file'),
        ];

        if ($hasSession) {
            try {
                $session = $this->sessionManager->getSession($this->connection);
                $status['session_id'] = substr($session->sessionId, 0, 8).'...';
                $status['remaining_ttl_seconds'] = $session->getRemainingTtl();
                $status['expires_at'] = $session->expiresAt->toIso8601String();
            } catch (\Throwable) {
                // Session info not available
            }
        }

        return $status;
    }

    /**
     * Get Service Layer information.
     *
     * @return array<string, mixed>
     */
    public function getServiceLayerInfo(): array
    {
        $companyService = new CompanyService($this->client, $this->connection);
        $slInfo = $companyService->serviceLayerInfo();

        return [
            'available' => $slInfo !== null,
            'database_type' => $slInfo['DatabaseType'] ?? 'unknown',
            'version' => $slInfo['Version'] ?? 'unknown',
            'is_hana' => $companyService->isHana(),
        ];
    }

    /**
     * Get company information.
     *
     * @return array<string, mixed>
     */
    public function getCompanyInfo(): array
    {
        $companyService = new CompanyService($this->client, $this->connection);

        return [
            'name' => $companyService->name(),
            'local_currency' => $companyService->localCurrency(),
            'country' => $companyService->country(),
            'version' => $companyService->version(),
            'multi_branch' => $companyService->isMultiBranch(),
        ];
    }

    /**
     * Get current configuration.
     *
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        return [
            'base_url' => config("sap-b1.connections.{$this->connection}.base_url"),
            'company_db' => config("sap-b1.connections.{$this->connection}.company_db"),
            'odata_version' => config("sap-b1.connections.{$this->connection}.odata_version", 'v1'),
            'timeout' => config('sap-b1.http.timeout', 30),
            'retry_times' => config('sap-b1.http.retry.times', 3),
            'circuit_breaker_enabled' => config('sap-b1.http.circuit_breaker.enabled', false),
            'cache_enabled' => config('sap-b1.cache.enabled', false),
            'pool_enabled' => config('sap-b1.pool.enabled', false),
        ];
    }

    /**
     * Get performance metrics from profiler.
     *
     * @return array<string, mixed>
     */
    public function getPerformanceMetrics(): array
    {
        if ($this->profiler === null) {
            return [];
        }

        $stats = $this->profiler->getStats();

        return [
            'total_queries' => $stats['total'],
            'slow_queries' => $stats['slow'],
            'average_time_ms' => $stats['average_duration'],
            'max_time_ms' => $stats['max_duration'],
            'min_time_ms' => $stats['min_duration'],
        ];
    }

    /**
     * Test connectivity with detailed results.
     *
     * @return array<string, mixed>
     */
    public function testConnectivity(): array
    {
        $tests = [];

        // Test 1: DNS Resolution
        /** @var string|null $baseUrl */
        $baseUrl = config("sap-b1.connections.{$this->connection}.base_url");

        /** @var string|false|null $hostParsed */
        $hostParsed = $baseUrl !== null ? parse_url($baseUrl, PHP_URL_HOST) : null;
        $host = is_string($hostParsed) ? $hostParsed : null;

        if ($host === null) {
            $tests['dns_resolution'] = [
                'success' => false,
                'host' => null,
                'ip' => null,
                'error' => 'Invalid base URL configuration',
                'time_ms' => 0.0,
            ];
        } else {
            $start = microtime(true);
            $ip = gethostbyname($host);
            $tests['dns_resolution'] = [
                'success' => $ip !== $host,
                'host' => $host,
                'ip' => $ip !== $host ? $ip : null,
                'time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        }

        // Test 2: TCP Connection
        $parsedPort = $baseUrl !== null ? parse_url($baseUrl, PHP_URL_PORT) : null;
        $port = is_int($parsedPort) ? $parsedPort : 50000;

        if ($host === null) {
            $tests['tcp_connection'] = [
                'success' => false,
                'port' => $port,
                'error' => 'Invalid host',
                'time_ms' => 0.0,
            ];
        } else {
            $start = microtime(true);
            $socket = @fsockopen($host, $port, $errno, $errstr, 5);
            $tests['tcp_connection'] = [
                'success' => $socket !== false,
                'port' => $port,
                'error' => $socket === false ? $errstr : null,
                'time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
            if ($socket !== false) {
                fclose($socket);
            }
        }

        // Test 3: HTTP Request
        $start = microtime(true);
        try {
            $response = $this->client
                ->connection($this->connection)
                ->get('Currencies?$top=1');

            $tests['http_request'] = [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            $tests['http_request'] = [
                'success' => false,
                'error' => $e->getMessage(),
                'time_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        }

        // Test 4: Authentication
        $start = microtime(true);
        $tests['authentication'] = [
            'success' => $this->sessionManager->hasValidSession($this->connection),
            'time_ms' => round((microtime(true) - $start) * 1000, 2),
        ];

        return [
            'all_passed' => collect($tests)->every(fn ($t) => $t['success']),
            'tests' => $tests,
        ];
    }

    /**
     * Use a different connection.
     */
    public function connection(string $connection): self
    {
        $clone = clone $this;
        $clone->connection = $connection;

        return $clone;
    }
}
