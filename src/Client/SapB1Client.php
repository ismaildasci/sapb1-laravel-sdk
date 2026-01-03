<?php

declare(strict_types=1);

namespace SapB1\Client;

use SapB1\Exceptions\AuthenticationException;
use SapB1\Exceptions\SessionExpiredException;
use SapB1\Session\SessionManager;

class SapB1Client
{
    protected string $connection = 'default';

    protected ?ODataBuilder $odata = null;

    /**
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Create a new SapB1Client instance.
     */
    public function __construct(
        protected SessionManager $sessionManager
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
        return $this->request()->get($endpoint);
    }

    /**
     * Find a single record by key.
     */
    public function find(string $endpoint, mixed $key): Response
    {
        $formattedKey = $this->formatKey($key);

        return $this->request()->get("{$endpoint}({$formattedKey})");
    }

    /**
     * Create a new record.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(string $endpoint, array $data): Response
    {
        return $this->request()->post($endpoint, $data);
    }

    /**
     * Update an existing record.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $endpoint, mixed $key, array $data): Response
    {
        $formattedKey = $this->formatKey($key);

        return $this->request()->patch("{$endpoint}({$formattedKey})", $data);
    }

    /**
     * Delete a record.
     */
    public function delete(string $endpoint, mixed $key): Response
    {
        $formattedKey = $this->formatKey($key);

        return $this->request()->delete("{$endpoint}({$formattedKey})");
    }

    /**
     * Call a SAP B1 action (POST to endpoint).
     *
     * @param  array<string, mixed>  $params
     */
    public function action(string $endpoint, mixed $key, string $action, array $params = []): Response
    {
        $formattedKey = $this->formatKey($key);

        return $this->request()->post("{$endpoint}({$formattedKey})/{$action}", $params);
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

        $endpoint = basename($path);

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
        return $this->request()->post($endpoint, $data);
    }

    /**
     * Raw PUT request.
     *
     * @param  array<string, mixed>  $data
     */
    public function put(string $endpoint, array $data = []): Response
    {
        return $this->request()->put($endpoint, $data);
    }

    /**
     * Raw PATCH request.
     *
     * @param  array<string, mixed>  $data
     */
    public function patch(string $endpoint, array $data = []): Response
    {
        return $this->request()->patch($endpoint, $data);
    }

    /**
     * Raw DELETE request (without key formatting).
     */
    public function rawDelete(string $endpoint): Response
    {
        return $this->request()->delete($endpoint);
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

        return $request;
    }

    /**
     * Get the session, handling expiration.
     */
    protected function getSession(): \SapB1\Session\SessionData
    {
        try {
            return $this->sessionManager->getSession($this->connection);
        } catch (AuthenticationException $e) {
            throw new SessionExpiredException(
                message: 'Session expired or invalid: '.$e->getMessage(),
                context: ['connection' => $this->connection]
            );
        }
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

        return rtrim($baseUrl, '/').'/b1s/v1';
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
