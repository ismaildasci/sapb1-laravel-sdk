<?php

declare(strict_types=1);

namespace SapB1\Client;

use SapB1\Contracts\SessionPoolInterface;
use SapB1\Exceptions\AuthenticationException;
use SapB1\Exceptions\PoolExhaustedException;
use SapB1\Exceptions\ServiceLayerException;
use SapB1\Exceptions\SessionExpiredException;
use SapB1\Session\SessionData;
use SapB1\Session\SessionManager;

class SapB1Client
{
    /**
     * Attachments manager instance.
     */
    protected ?AttachmentsManager $attachmentsManager = null;

    protected string $connection = 'default';

    protected ?ODataBuilder $odata = null;

    /**
     * Whether to automatically refresh session on 401 errors.
     */
    protected bool $autoRefreshSession = true;

    /**
     * Number of session refresh retries attempted.
     */
    protected int $sessionRefreshAttempts = 0;

    /**
     * Maximum session refresh retry attempts.
     */
    protected int $maxSessionRefreshAttempts = 1;

    /**
     * OData version to use ('v1' for OData v3, 'v2' for OData v4).
     */
    protected string $odataVersion = 'v1';

    /**
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Currently acquired session from pool (for release).
     */
    protected ?SessionData $acquiredSession = null;

    /**
     * Create a new SapB1Client instance.
     */
    public function __construct(
        protected SessionManager $sessionManager,
        protected ?SessionPoolInterface $pool = null
    ) {
        $this->loadConfig();
    }

    /**
     * Use a specific connection.
     */
    public function connection(string $connection): self
    {
        $clone = clone $this;
        $clone->connection = $connection;
        $clone->acquiredSession = null;
        $clone->loadConfig();

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
     * Get a fluent service endpoint for an entity.
     *
     * @example $client->service('BusinessPartners')->find('C001')
     * @example $client->service('Orders')->create(['CardCode' => 'C001'])
     */
    public function service(string $endpoint): ServiceEndpoint
    {
        return new ServiceEndpoint($this, $endpoint);
    }

    /**
     * Create a new batch request.
     *
     * @example $client->batch()->post('Orders', $data)->post('DeliveryNotes', $data)->execute()
     */
    public function batch(): BatchRequest
    {
        return new BatchRequest(
            $this->getBaseUrl(),
            $this->getSession()
        );
    }

    /**
     * Get the attachments manager.
     *
     * @example $client->attachments()->upload('Orders', 123, $file)
     */
    public function attachments(): AttachmentsManager
    {
        if ($this->attachmentsManager === null) {
            $this->attachmentsManager = new AttachmentsManager($this);
        }

        return $this->attachmentsManager;
    }

    /**
     * Create a new SQL query builder.
     *
     * @example $client->sql('MyStoredQuery')->param('CardCode', 'C001')->execute()
     */
    public function sql(string $queryName): SqlQueryBuilder
    {
        return new SqlQueryBuilder($this, $queryName);
    }

    /**
     * Create a new semantic layer query.
     *
     * @example $client->semantic('SalesAnalysis')->dimensions('CardCode', 'ItemCode')->measures('DocTotal')->execute()
     */
    public function semantic(string $queryName): SemanticLayerClient
    {
        return new SemanticLayerClient($this, $queryName);
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
        return $this->executeWithAutoRefresh(fn () => $this->request()->get($endpoint));
    }

    /**
     * Find a single record by key.
     */
    public function find(string $endpoint, mixed $key): Response
    {
        $formattedKey = $this->formatKey($key);

        return $this->executeWithAutoRefresh(fn () => $this->request()->get("{$endpoint}({$formattedKey})"));
    }

    /**
     * Create a new record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(string $endpoint, array $data): Response
    {
        return $this->executeWithAutoRefresh(fn () => $this->request()->post($endpoint, $data));
    }

    /**
     * Update an existing record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $endpoint, mixed $key, array $data): Response
    {
        $formattedKey = $this->formatKey($key);

        return $this->executeWithAutoRefresh(fn () => $this->request()->patch("{$endpoint}({$formattedKey})", $data));
    }

    /**
     * Delete a record.
     */
    public function delete(string $endpoint, mixed $key): Response
    {
        $formattedKey = $this->formatKey($key);

        return $this->executeWithAutoRefresh(fn () => $this->request()->delete("{$endpoint}({$formattedKey})"));
    }

    /**
     * Call a SAP B1 action (POST to endpoint).
     *
     * @param  array<string, mixed>  $params
     */
    public function action(string $endpoint, mixed $key, string $action, array $params = []): Response
    {
        $formattedKey = $this->formatKey($key);

        return $this->executeWithAutoRefresh(fn () => $this->request()->post("{$endpoint}({$formattedKey})/{$action}", $params));
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

        // Extract the relative path from nextLink
        $path = parse_url($nextLink, PHP_URL_PATH);
        $query = parse_url($nextLink, PHP_URL_QUERY);

        if ($path === false || $path === null) {
            return null;
        }

        // Remove the /b1s/v1/ prefix from the path to get the endpoint
        // Example: /b1s/v1/BusinessPartners -> BusinessPartners
        $endpoint = preg_replace('#^/b1s/v\d+/#', '', $path);

        if ($endpoint === null || $endpoint === '' || $endpoint === $path) {
            // Fallback: try to get the last segment if regex fails
            $segments = explode('/', trim($path, '/'));
            $endpoint = end($segments) ?: '';
        }

        if ($query !== false && $query !== null) {
            $endpoint .= '?'.$query;
        }

        return $this->createRequest()->get($endpoint);
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
        $response = $this->request()
            ->withOData(ODataBuilder::make()->inlineCount()->top(1))
            ->get($endpoint);

        return $response->count() ?? 0;
    }

    /**
     * Check if a record exists.
     */
    public function exists(string $endpoint, mixed $key): bool
    {
        try {
            $response = $this->find($endpoint, $key);

            return $response->successful();
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Raw POST request.
     *
     * @param  array<string, mixed>  $data
     */
    public function post(string $endpoint, array $data = []): Response
    {
        return $this->executeWithAutoRefresh(fn () => $this->request()->post($endpoint, $data));
    }

    /**
     * Raw PUT request.
     *
     * @param  array<string, mixed>  $data
     */
    public function put(string $endpoint, array $data = []): Response
    {
        return $this->executeWithAutoRefresh(fn () => $this->request()->put($endpoint, $data));
    }

    /**
     * Raw PATCH request.
     *
     * @param  array<string, mixed>  $data
     */
    public function patch(string $endpoint, array $data = []): Response
    {
        return $this->executeWithAutoRefresh(fn () => $this->request()->patch($endpoint, $data));
    }

    /**
     * Raw DELETE request (without key formatting).
     */
    public function rawDelete(string $endpoint): Response
    {
        return $this->executeWithAutoRefresh(fn () => $this->request()->delete($endpoint));
    }

    /**
     * Logout from SAP B1.
     */
    public function logout(): void
    {
        $this->sessionManager->logout($this->connection);
    }

    /**
     * Refresh the session.
     */
    public function refreshSession(): void
    {
        $this->sessionManager->refreshSession($this->connection);
    }

    /**
     * Check if session is valid.
     */
    public function hasValidSession(): bool
    {
        return $this->sessionManager->hasValidSession($this->connection);
    }

    /**
     * Get the current connection name.
     */
    public function getConnection(): string
    {
        return $this->connection;
    }

    /**
     * Enable or disable automatic session refresh on 401 errors.
     */
    public function withAutoRefresh(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->autoRefreshSession = $enabled;

        return $clone;
    }

    /**
     * Disable automatic session refresh.
     */
    public function withoutAutoRefresh(): self
    {
        return $this->withAutoRefresh(false);
    }

    /**
     * Set the OData version to use.
     *
     * @param  string  $version  'v1' for OData v3, 'v2' for OData v4
     */
    public function withODataVersion(string $version): self
    {
        $clone = clone $this;
        $clone->odataVersion = $version;

        return $clone;
    }

    /**
     * Use OData v4 (Service Layer v2 endpoint).
     */
    public function useODataV4(): self
    {
        return $this->withODataVersion('v2');
    }

    /**
     * Use OData v3 (Service Layer v1 endpoint).
     */
    public function useODataV3(): self
    {
        return $this->withODataVersion('v1');
    }

    /**
     * Get the current OData version.
     */
    public function getODataVersion(): string
    {
        // Instance property takes priority if explicitly set (not default 'v1')
        if ($this->odataVersion !== 'v1') {
            return $this->odataVersion;
        }

        // Otherwise use config, fallback to instance property
        return $this->config['odata_version'] ?? $this->odataVersion;
    }

    /**
     * Execute a request with automatic session refresh on 401 errors.
     *
     * @param  callable(): Response  $callback
     */
    protected function executeWithAutoRefresh(callable $callback): Response
    {
        $this->sessionRefreshAttempts = 0;

        try {
            return $callback();
        } catch (ServiceLayerException $e) {
            if (! $this->autoRefreshSession) {
                throw $e;
            }

            if ($e->getStatusCode() !== 401) {
                throw $e;
            }

            // Check if this is a session error
            if (! $this->sessionManager->isSessionError($e->context['response'] ?? null)) {
                throw $e;
            }

            if ($this->sessionRefreshAttempts >= $this->maxSessionRefreshAttempts) {
                throw new SessionExpiredException(
                    message: 'Session expired and refresh attempts exhausted: '.$e->getMessage(),
                    context: ['connection' => $this->connection]
                );
            }

            $this->sessionRefreshAttempts++;

            // Invalidate and refresh the session
            $this->sessionManager->invalidateAndRefresh($this->connection);

            // Retry the request
            return $callback();
        }
    }

    /**
     * Create a PendingRequest with session and apply OData.
     */
    protected function request(): PendingRequest
    {
        $request = $this->createRequest();

        if ($this->odata !== null) {
            $request->withOData($this->odata);
            $this->odata = null;
        }

        return $request;
    }

    /**
     * Create a configured PendingRequest.
     */
    protected function createRequest(): PendingRequest
    {
        $session = $this->getSession();

        $request = new PendingRequest;

        $request
            ->baseUrl($this->getBaseUrl())
            ->connection($this->connection)
            ->withSessionHeaders($session->getHeaders())
            ->timeout($this->getTimeout())
            ->connectTimeout($this->getConnectTimeout())
            ->verify($this->getVerify())
            ->retry(
                $this->getRetryTimes(),
                $this->getRetrySleep(),
                $this->getRetryWhen()
            );

        // Apply auto request ID if enabled
        if ($this->isAutoRequestIdEnabled()) {
            $request->withRequestId();
        }

        // Apply circuit breaker if enabled
        if ($this->isCircuitBreakerEnabled()) {
            $request->withCircuitBreaker();
        }

        return $request;
    }

    /**
     * Check if auto request ID is enabled.
     */
    protected function isAutoRequestIdEnabled(): bool
    {
        return (bool) config('sap-b1.http.request_id.auto', false);
    }

    /**
     * Check if circuit breaker is enabled.
     */
    protected function isCircuitBreakerEnabled(): bool
    {
        return (bool) config('sap-b1.http.circuit_breaker.enabled', false);
    }

    /**
     * Get the session, handling expiration.
     * Uses pool if available and enabled, otherwise falls back to session manager.
     */
    protected function getSession(): SessionData
    {
        try {
            // Use pool if available
            if ($this->pool !== null && config('sap-b1.pool.enabled', false)) {
                $session = $this->pool->acquire(
                    $this->connection,
                    (int) config('sap-b1.pool.connections.'.$this->connection.'.wait_timeout', 30)
                );

                if ($session === null) {
                    throw PoolExhaustedException::forConnection(
                        $this->connection,
                        (int) config('sap-b1.pool.connections.'.$this->connection.'.wait_timeout', 30)
                    );
                }

                $this->acquiredSession = $session;

                return $session;
            }

            // Default: use session manager (1-to-1 session)
            return $this->sessionManager->getSession($this->connection);
        } catch (AuthenticationException $e) {
            throw new SessionExpiredException(
                message: 'Session expired or invalid: '.$e->getMessage(),
                context: ['connection' => $this->connection]
            );
        }
    }

    /**
     * Release the acquired session back to the pool.
     * Only applicable when using session pool.
     */
    public function releaseSession(bool $invalidate = false): void
    {
        if ($this->pool !== null && $this->acquiredSession !== null) {
            $this->pool->release($this->connection, $this->acquiredSession, $invalidate);
            $this->acquiredSession = null;
        }
    }

    /**
     * Check if currently using session pool.
     */
    public function isUsingPool(): bool
    {
        return $this->pool !== null && config('sap-b1.pool.enabled', false);
    }

    /**
     * Get pool statistics for the current connection.
     *
     * @return array<string, mixed>|null
     */
    public function getPoolStats(): ?array
    {
        if ($this->pool === null) {
            return null;
        }

        return $this->pool->stats($this->connection);
    }

    /**
     * Load configuration for the current connection.
     */
    protected function loadConfig(): void
    {
        /** @var array<string, mixed> $config */
        $config = config("sap-b1.connections.{$this->connection}", []);
        $this->config = $config;
    }

    /**
     * Get the base URL for API requests.
     */
    protected function getBaseUrl(): string
    {
        /** @var string $baseUrl */
        $baseUrl = $this->config['base_url'] ?? '';

        $version = $this->getODataVersion();

        return rtrim($baseUrl, '/').'/b1s/'.$version;
    }

    /**
     * Get the request timeout.
     */
    protected function getTimeout(): int
    {
        return (int) config('sap-b1.http.timeout', 30);
    }

    /**
     * Get the connection timeout.
     */
    protected function getConnectTimeout(): int
    {
        return (int) config('sap-b1.http.connect_timeout', 10);
    }

    /**
     * Get SSL verification setting.
     */
    protected function getVerify(): bool
    {
        return (bool) config('sap-b1.http.verify', true);
    }

    /**
     * Get retry times.
     */
    protected function getRetryTimes(): int
    {
        return (int) config('sap-b1.http.retry.times', 3);
    }

    /**
     * Get retry sleep in milliseconds.
     */
    protected function getRetrySleep(): int
    {
        return (int) config('sap-b1.http.retry.sleep', 1000);
    }

    /**
     * Get status codes to retry.
     *
     * @return array<int, int>
     */
    protected function getRetryWhen(): array
    {
        /** @var array<int, int> $when */
        $when = config('sap-b1.http.retry.when', [500, 502, 503, 504]);

        return $when;
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
            // Composite key
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
