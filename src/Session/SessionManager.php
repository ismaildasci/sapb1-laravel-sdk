<?php

declare(strict_types=1);

namespace SapB1\Session;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SapB1\Contracts\SessionStoreInterface;
use SapB1\Events\SessionCreated;
use SapB1\Events\SessionExpired;
use SapB1\Exceptions\AuthenticationException;
use SapB1\Exceptions\ConnectionException;

class SessionManager
{
    public function __construct(
        protected SessionStoreInterface $store
    ) {}

    /**
     * Get or create a session for the given connection.
     */
    public function getSession(string $connection = 'default'): SessionData
    {
        $session = $this->store->get($connection);

        if ($session === null) {
            return $this->login($connection);
        }

        if ($this->store->needsRefresh($connection)) {
            return $this->refreshWithLock($connection);
        }

        return $session;
    }

    /**
     * Force refresh the session for the given connection.
     */
    public function refreshSession(string $connection = 'default'): SessionData
    {
        return $this->refreshWithLock($connection);
    }

    /**
     * Logout from the given connection.
     */
    public function logout(string $connection = 'default'): void
    {
        $session = $this->store->get($connection);

        if ($session !== null) {
            try {
                $config = $this->getConnectionConfig($connection);
                $client = $this->getHttpClient($config);

                $client->post('Logout', [
                    'headers' => $session->getHeaders(),
                ]);
            } catch (GuzzleException) {
                // Ignore logout errors
            }

            $this->store->forget($connection);
            SessionExpired::dispatch($connection, $session->sessionId);
        }
    }

    /**
     * Get session headers for API requests.
     *
     * @return array<string, string>
     */
    public function getSessionHeaders(SessionData $session): array
    {
        return $session->getHeaders();
    }

    /**
     * Check if a valid session exists for the given connection.
     */
    public function hasValidSession(string $connection = 'default'): bool
    {
        $session = $this->store->get($connection);

        return $session !== null && ! $session->isExpired();
    }

    /**
     * Clear session for the given connection.
     */
    public function clearSession(string $connection = 'default'): void
    {
        $session = $this->store->get($connection);
        $this->store->forget($connection);

        if ($session !== null) {
            SessionExpired::dispatch($connection, $session->sessionId);
        }
    }

    /**
     * Invalidate and refresh the session for the given connection.
     * Used when a 401 error indicates the session is no longer valid.
     */
    public function invalidateAndRefresh(string $connection = 'default'): SessionData
    {
        $session = $this->store->get($connection);
        $this->store->forget($connection);

        if ($session !== null) {
            SessionExpired::dispatch($connection, $session->sessionId);
        }

        return $this->login($connection);
    }

    /**
     * Check if the given error response indicates a session error.
     *
     * @param  array<string, mixed>|null  $responseData
     */
    public function isSessionError(?array $responseData): bool
    {
        if ($responseData === null) {
            return false;
        }

        $errorCode = $responseData['error']['code'] ?? null;
        $errorMessage = $responseData['error']['message']['value']
            ?? $responseData['error']['message']
            ?? '';

        // SAP B1 Session Error Codes
        $sessionErrorCodes = [
            301,   // Invalid session
            -301,  // Session expired
        ];

        // Check error code
        if ($errorCode !== null && in_array((int) $errorCode, $sessionErrorCodes, true)) {
            return true;
        }

        // Check error message for session-related keywords
        $sessionKeywords = ['session', 'Session', 'login', 'Login', 'unauthorized', 'Unauthorized'];
        foreach ($sessionKeywords as $keyword) {
            if (str_contains((string) $errorMessage, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear all stored sessions.
     */
    public function clearAllSessions(): void
    {
        $this->store->flush();
    }

    /**
     * Refresh session with lock to prevent concurrent refresh.
     */
    protected function refreshWithLock(string $connection): SessionData
    {
        $lockTimeout = (int) config('sap-b1.session.lock.timeout', 10);

        if (! $this->store->acquireLock($connection, $lockTimeout)) {
            // Another process is refreshing, wait and get the session
            usleep(500000); // 500ms

            $session = $this->store->get($connection);

            if ($session !== null && ! $session->isExpired()) {
                return $session;
            }
        }

        try {
            return $this->doRefresh($connection);
        } finally {
            $this->store->releaseLock($connection);
        }
    }

    /**
     * Perform the actual session refresh.
     */
    protected function doRefresh(string $connection): SessionData
    {
        // Double-check if another process already refreshed
        $session = $this->store->get($connection);

        if ($session !== null && ! $session->isNearExpiry()) {
            return $session;
        }

        // Perform new login
        return $this->login($connection);
    }

    /**
     * Login to SAP B1 Service Layer.
     */
    protected function login(string $connection): SessionData
    {
        $config = $this->getConnectionConfig($connection);
        $client = $this->getHttpClient($config);

        try {
            $response = $client->post('Login', [
                'json' => [
                    'CompanyDB' => $config['company_db'],
                    'UserName' => $config['username'],
                    'Password' => $config['password'],
                    'Language' => $config['language'] ?? 23,
                ],
            ]);

            /** @var array<string, mixed> $data */
            $data = json_decode((string) $response->getBody(), true);

            $ttl = (int) config('sap-b1.session.ttl', 1680);
            $session = SessionData::fromLoginResponse($data, $config['company_db'], $ttl);

            $this->store->put($connection, $session);

            SessionCreated::dispatch($connection, $session->sessionId, $session->companyDb);

            return $session;
        } catch (GuzzleException $e) {
            throw new AuthenticationException(
                message: 'Failed to login to SAP B1: '.$e->getMessage(),
                context: ['connection' => $connection]
            );
        }
    }

    /**
     * Get connection configuration.
     *
     * @return array<string, mixed>
     */
    protected function getConnectionConfig(string $connection): array
    {
        /** @var array<string, mixed>|null $config */
        $config = config("sap-b1.connections.{$connection}");

        if ($config === null) {
            throw new ConnectionException(
                message: "SAP B1 connection [{$connection}] not configured",
                context: ['connection' => $connection]
            );
        }

        return $config;
    }

    /**
     * Create HTTP client for the given connection config.
     *
     * @param  array<string, mixed>  $config
     */
    protected function getHttpClient(array $config): Client
    {
        return new Client([
            'base_uri' => rtrim($config['base_url'] ?? '', '/').'/b1s/v1/',
            'timeout' => config('sap-b1.http.timeout', 30),
            'connect_timeout' => config('sap-b1.http.connect_timeout', 10),
            'verify' => config('sap-b1.http.verify', true),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }
}
