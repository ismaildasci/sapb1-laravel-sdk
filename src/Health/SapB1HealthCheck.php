<?php

declare(strict_types=1);

namespace SapB1\Health;

use SapB1\Client\SapB1Client;
use SapB1\Exceptions\AuthenticationException;
use SapB1\Exceptions\ConnectionException;
use SapB1\Session\SessionManager;

class SapB1HealthCheck
{
    public function __construct(
        protected SessionManager $sessionManager,
        protected SapB1Client $client
    ) {}

    /**
     * Check the health of a SAP B1 connection.
     */
    public function check(?string $connection = null): HealthCheckResult
    {
        $connection ??= $this->getDefaultConnection();

        try {
            return $this->performCheck($connection);
        } catch (AuthenticationException $e) {
            return HealthCheckResult::unhealthy(
                message: 'Authentication failed: '.$e->getMessage(),
                connection: $connection
            );
        } catch (ConnectionException $e) {
            return HealthCheckResult::unhealthy(
                message: 'Connection failed: '.$e->getMessage(),
                connection: $connection
            );
        } catch (\Exception $e) {
            return HealthCheckResult::unhealthy(
                message: 'Health check failed: '.$e->getMessage(),
                connection: $connection
            );
        }
    }

    /**
     * Check multiple connections.
     *
     * @param  array<int, string>|null  $connections
     * @return array<string, HealthCheckResult>
     */
    public function checkAll(?array $connections = null): array
    {
        $connections ??= $this->getAllConnections();
        $results = [];

        foreach ($connections as $connection) {
            $results[$connection] = $this->check($connection);
        }

        return $results;
    }

    /**
     * Check if all connections are healthy.
     *
     * @param  array<int, string>|null  $connections
     */
    public function isHealthy(?array $connections = null): bool
    {
        $results = $this->checkAll($connections);

        foreach ($results as $result) {
            if ($result->isUnhealthy()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Perform the actual health check.
     */
    protected function performCheck(string $connection): HealthCheckResult
    {
        $startTime = microtime(true);

        // First, check if we can get/create a session
        $session = $this->sessionManager->getSession($connection);

        // Then, try a simple API call
        $connectionClient = $connection !== $this->getDefaultConnection()
            ? $this->client->connection($connection)
            : $this->client;

        $response = $connectionClient->get('CompanyService_GetCompanyInfo');

        $responseTime = (microtime(true) - $startTime) * 1000;

        if ($response->failed()) {
            return HealthCheckResult::unhealthy(
                message: 'API call failed: '.($response->errorMessage() ?? 'Unknown error'),
                connection: $connection
            );
        }

        return HealthCheckResult::healthy(
            message: 'SAP B1 connection is healthy',
            responseTime: $responseTime,
            connection: $connection,
            companyDb: $session->companyDb,
            sessionId: $session->sessionId
        );
    }

    /**
     * Get the default connection name.
     */
    protected function getDefaultConnection(): string
    {
        /** @var string $connection */
        $connection = config('sap-b1.default', 'default');

        return $connection;
    }

    /**
     * Get all configured connection names.
     *
     * @return array<int, string>
     */
    protected function getAllConnections(): array
    {
        /** @var array<string, mixed> $connections */
        $connections = config('sap-b1.connections', []);

        return array_keys($connections);
    }
}
