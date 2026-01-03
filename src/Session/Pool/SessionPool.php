<?php

declare(strict_types=1);

namespace SapB1\Session\Pool;

use SapB1\Contracts\SessionPoolInterface;
use SapB1\Contracts\SessionPoolStoreInterface;
use SapB1\Events\PoolSessionExpired;
use SapB1\Events\PoolWarmedUp;
use SapB1\Events\SessionAcquired;
use SapB1\Events\SessionReleased;
use SapB1\Session\SessionData;
use SapB1\Session\SessionManager;

/**
 * Session pool implementation.
 *
 * Manages a pool of SAP B1 sessions for high-concurrency scenarios,
 * providing automatic acquisition, release, and lifecycle management.
 */
class SessionPool implements SessionPoolInterface
{
    /**
     * @var array<string, PoolConfiguration>
     */
    protected array $configurations = [];

    public function __construct(
        protected SessionPoolStoreInterface $store,
        protected SessionManager $sessionManager
    ) {}

    public function acquire(string $connection, int $timeout = 30): ?SessionData
    {
        $config = $this->getConfiguration($connection);
        $algorithm = $config->algorithm;
        $startTime = time();

        while (true) {
            // Try to acquire an idle session
            $session = $this->store->acquireNext($connection, $algorithm);

            if ($session !== null) {
                // Validate session if configured
                if ($config->validationOnAcquire && $session->session->isExpired()) {
                    $this->store->markExpired($connection, $session->getSessionId());
                    PoolSessionExpired::dispatch($connection, $session->getSessionId());

                    continue;
                }

                SessionAcquired::dispatch($connection, $session->getSessionId());

                return $session->session;
            }

            // Check if we can create a new session
            $currentSize = $this->store->count($connection);
            if ($currentSize < $config->maxSize) {
                $newSession = $this->createSession($connection);
                if ($newSession !== null) {
                    // Store and immediately acquire the new session
                    $pooledSession = PooledSession::fromSession($newSession);
                    $this->store->store($connection, $pooledSession);

                    // Acquire it
                    $acquired = $this->store->acquireNext($connection, $algorithm);
                    if ($acquired !== null) {
                        SessionAcquired::dispatch($connection, $acquired->getSessionId());

                        return $acquired->session;
                    }
                }
            }

            // Check timeout
            if ($timeout > 0 && (time() - $startTime) >= $timeout) {
                return null;
            }

            // Wait and retry
            if ($timeout > 0) {
                usleep(100000); // 100ms
            } else {
                break;
            }
        }

        return null;
    }

    public function release(string $connection, SessionData $session, bool $invalidate = false): void
    {
        if ($invalidate) {
            $this->store->markExpired($connection, $session->sessionId);
            PoolSessionExpired::dispatch($connection, $session->sessionId);
        } else {
            $this->store->release($connection, $session->sessionId);
        }

        SessionReleased::dispatch($connection, $session->sessionId);
    }

    public function size(string $connection): int
    {
        return $this->store->count($connection);
    }

    public function available(string $connection): int
    {
        return $this->store->countByStatus($connection, PooledSession::STATUS_IDLE);
    }

    public function active(string $connection): int
    {
        return $this->store->countByStatus($connection, PooledSession::STATUS_ACTIVE);
    }

    public function stats(string $connection): array
    {
        $config = $this->getConfiguration($connection);

        return [
            'total' => $this->store->count($connection),
            'active' => $this->store->countByStatus($connection, PooledSession::STATUS_ACTIVE),
            'idle' => $this->store->countByStatus($connection, PooledSession::STATUS_IDLE),
            'expired' => $this->store->countByStatus($connection, PooledSession::STATUS_EXPIRED),
            'waiting' => 0, // Could track this with a counter
            'min_size' => $config->minSize,
            'max_size' => $config->maxSize,
            'algorithm' => $config->algorithm,
        ];
    }

    public function warmUp(string $connection, ?int $count = null): int
    {
        $config = $this->getConfiguration($connection);
        $targetCount = $count ?? $config->minSize;
        $currentCount = $this->store->count($connection);
        $toCreate = max(0, $targetCount - $currentCount);
        $created = 0;

        for ($i = 0; $i < $toCreate; $i++) {
            $session = $this->createSession($connection);
            if ($session !== null) {
                $pooledSession = PooledSession::fromSession($session);
                $this->store->store($connection, $pooledSession);
                $created++;
            }
        }

        if ($created > 0) {
            PoolWarmedUp::dispatch($connection, $created);
        }

        return $created;
    }

    public function drain(string $connection): int
    {
        // Get count before removal
        $count = $this->store->count($connection);

        // Remove all sessions from store
        // Note: Sessions in SAP B1 expire naturally, no need for explicit logout
        $this->store->removeAll($connection);

        return $count;
    }

    public function cleanup(string $connection): int
    {
        $removed = 0;

        // Remove expired sessions
        $removed += $this->store->removeExpired($connection);

        // Check idle sessions for expiry
        foreach ($this->store->getIdle($connection) as $session) {
            if ($session->session->isExpired()) {
                $this->store->remove($connection, $session->getSessionId());
                PoolSessionExpired::dispatch($connection, $session->getSessionId());
                $removed++;
            }
        }

        // Ensure minimum pool size after cleanup
        $config = $this->getConfiguration($connection);
        $currentSize = $this->store->count($connection);
        if ($currentSize < $config->minSize) {
            $this->warmUp($connection, $config->minSize);
        }

        return $removed;
    }

    /**
     * Get configuration for a connection.
     */
    protected function getConfiguration(string $connection): PoolConfiguration
    {
        if (! isset($this->configurations[$connection])) {
            $this->configurations[$connection] = PoolConfiguration::forConnection($connection);
        }

        return $this->configurations[$connection];
    }

    /**
     * Create a new session using the session manager.
     */
    protected function createSession(string $connection): ?SessionData
    {
        try {
            return $this->sessionManager->createNewSession($connection);
        } catch (\Throwable) {
            return null;
        }
    }
}
