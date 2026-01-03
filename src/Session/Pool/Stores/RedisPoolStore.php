<?php

declare(strict_types=1);

namespace SapB1\Session\Pool\Stores;

use Carbon\CarbonImmutable;
use Illuminate\Redis\RedisManager;
use SapB1\Contracts\SessionPoolStoreInterface;
use SapB1\Session\Pool\Algorithms\DistributionAlgorithm;
use SapB1\Session\Pool\Algorithms\LeastConnectionsAlgorithm;
use SapB1\Session\Pool\Algorithms\LifoAlgorithm;
use SapB1\Session\Pool\Algorithms\RoundRobinAlgorithm;
use SapB1\Session\Pool\PooledSession;

/**
 * Redis-based session pool storage.
 *
 * Uses Redis Hash for storing session data and Sets for tracking
 * status with atomic operations for concurrent access.
 */
class RedisPoolStore implements SessionPoolStoreInterface
{
    protected string $prefix;

    protected int $ttl;

    /**
     * @var array<string, DistributionAlgorithm>
     */
    protected array $algorithms = [];

    public function __construct(
        protected RedisManager $redis,
        protected string $redisConnection = 'default'
    ) {
        $this->prefix = 'sap_b1_pool:';
        $this->ttl = (int) config('sap-b1.session.ttl', 1680);
        $this->algorithms = [
            'round_robin' => new RoundRobinAlgorithm,
            'least_connections' => new LeastConnectionsAlgorithm,
            'lifo' => new LifoAlgorithm,
        ];
    }

    public function store(string $connection, PooledSession $session): void
    {
        $sessionId = $session->getSessionId();

        // Store session data in hash
        $this->connection()->hset(
            $this->getPoolKey($connection),
            $sessionId,
            json_encode($session->toArray())
        );

        // Add to status set
        $this->connection()->sadd(
            $this->getStatusKey($connection, $session->status),
            $sessionId
        );

        // Set TTL on the pool hash
        $this->connection()->expire($this->getPoolKey($connection), $this->ttl + 300);
    }

    public function remove(string $connection, string $sessionId): void
    {
        // Remove from pool hash
        $this->connection()->hdel($this->getPoolKey($connection), $sessionId);

        // Remove from all status sets
        foreach ([PooledSession::STATUS_IDLE, PooledSession::STATUS_ACTIVE, PooledSession::STATUS_EXPIRED] as $status) {
            $this->connection()->srem($this->getStatusKey($connection, $status), $sessionId);
        }
    }

    public function acquireNext(string $connection, string $algorithm): ?PooledSession
    {
        // Get all idle sessions
        $idleSessions = $this->getIdle($connection);

        if (empty($idleSessions)) {
            return null;
        }

        // Use algorithm to select next session
        $algorithmInstance = $this->algorithms[$algorithm] ?? $this->algorithms['round_robin'];
        $selected = $algorithmInstance->select($idleSessions);

        if ($selected === null) {
            return null;
        }

        $sessionId = $selected->getSessionId();

        // Atomically move from idle to active set
        $moved = $this->connection()->smove(
            $this->getStatusKey($connection, PooledSession::STATUS_IDLE),
            $this->getStatusKey($connection, PooledSession::STATUS_ACTIVE),
            $sessionId
        );

        if (! $moved) {
            // Session was acquired by another process, try again
            return $this->acquireNext($connection, $algorithm);
        }

        // Update session data with acquired status
        $acquired = $selected->withAcquired();
        $this->connection()->hset(
            $this->getPoolKey($connection),
            $sessionId,
            json_encode($acquired->toArray())
        );

        return $acquired;
    }

    public function release(string $connection, string $sessionId): void
    {
        // Move from active to idle set
        $this->connection()->smove(
            $this->getStatusKey($connection, PooledSession::STATUS_ACTIVE),
            $this->getStatusKey($connection, PooledSession::STATUS_IDLE),
            $sessionId
        );

        // Update session data
        $session = $this->get($connection, $sessionId);
        if ($session !== null) {
            $released = $session->withReleased();
            $this->connection()->hset(
                $this->getPoolKey($connection),
                $sessionId,
                json_encode($released->toArray())
            );
        }
    }

    public function markExpired(string $connection, string $sessionId): void
    {
        // Move from current status to expired set
        foreach ([PooledSession::STATUS_IDLE, PooledSession::STATUS_ACTIVE] as $status) {
            $this->connection()->smove(
                $this->getStatusKey($connection, $status),
                $this->getStatusKey($connection, PooledSession::STATUS_EXPIRED),
                $sessionId
            );
        }

        // Update session data
        $session = $this->get($connection, $sessionId);
        if ($session !== null) {
            $expired = $session->withExpired();
            $this->connection()->hset(
                $this->getPoolKey($connection),
                $sessionId,
                json_encode($expired->toArray())
            );
        }
    }

    public function getAll(string $connection): array
    {
        /** @var array<string, string> $data */
        $data = $this->connection()->hgetall($this->getPoolKey($connection));

        if (empty($data)) {
            return [];
        }

        $sessions = [];
        foreach ($data as $json) {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($json, true) ?? [];
            $sessions[] = PooledSession::fromArray($decoded);
        }

        return $sessions;
    }

    public function getIdle(string $connection): array
    {
        return $this->getByStatus($connection, PooledSession::STATUS_IDLE);
    }

    public function getActive(string $connection): array
    {
        return $this->getByStatus($connection, PooledSession::STATUS_ACTIVE);
    }

    public function get(string $connection, string $sessionId): ?PooledSession
    {
        /** @var string|false $data */
        $data = $this->connection()->hget($this->getPoolKey($connection), $sessionId);

        if ($data === false) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($data, true) ?? [];

        return PooledSession::fromArray($decoded);
    }

    public function update(string $connection, PooledSession $session): void
    {
        $sessionId = $session->getSessionId();

        // Get current status
        $existing = $this->get($connection, $sessionId);
        $oldStatus = $existing !== null ? $existing->status : PooledSession::STATUS_IDLE;

        // Update hash
        $this->connection()->hset(
            $this->getPoolKey($connection),
            $sessionId,
            json_encode($session->toArray())
        );

        // Update status set if changed
        if ($oldStatus !== $session->status) {
            $this->connection()->smove(
                $this->getStatusKey($connection, $oldStatus),
                $this->getStatusKey($connection, $session->status),
                $sessionId
            );
        }
    }

    public function count(string $connection): int
    {
        return (int) $this->connection()->hlen($this->getPoolKey($connection));
    }

    public function countByStatus(string $connection, string $status): int
    {
        return (int) $this->connection()->scard($this->getStatusKey($connection, $status));
    }

    public function removeExpired(string $connection): int
    {
        $removed = 0;
        $now = CarbonImmutable::now();

        foreach ($this->getAll($connection) as $session) {
            if ($session->isExpired() || $session->session->expiresAt->lt($now)) {
                $this->remove($connection, $session->getSessionId());
                $removed++;
            }
        }

        return $removed;
    }

    public function removeAll(string $connection): int
    {
        $count = $this->count($connection);

        // Delete pool hash
        $this->connection()->del($this->getPoolKey($connection));

        // Delete status sets
        foreach ([PooledSession::STATUS_IDLE, PooledSession::STATUS_ACTIVE, PooledSession::STATUS_EXPIRED] as $status) {
            $this->connection()->del($this->getStatusKey($connection, $status));
        }

        return $count;
    }

    public function acquireLock(string $connection, int $timeout = 10): bool
    {
        /** @var bool|null $result */
        $result = $this->connection()->command('set', [
            $this->getLockKey($connection),
            '1',
            'EX',
            $timeout,
            'NX',
        ]);

        return (bool) $result;
    }

    public function releaseLock(string $connection): void
    {
        $this->connection()->del($this->getLockKey($connection));
    }

    /**
     * Get sessions by status.
     *
     * @return array<PooledSession>
     */
    protected function getByStatus(string $connection, string $status): array
    {
        $sessionIds = $this->connection()->smembers($this->getStatusKey($connection, $status));

        if (empty($sessionIds)) {
            return [];
        }

        $sessions = [];
        foreach ($sessionIds as $sessionId) {
            $session = $this->get($connection, (string) $sessionId);
            if ($session !== null && $session->status === $status) {
                $sessions[] = $session;
            }
        }

        return $sessions;
    }

    /**
     * @return \Illuminate\Redis\Connections\Connection
     */
    protected function connection()
    {
        return $this->redis->connection($this->redisConnection);
    }

    protected function getPoolKey(string $connection): string
    {
        return $this->prefix.$connection.':sessions';
    }

    protected function getStatusKey(string $connection, string $status): string
    {
        return $this->prefix.$connection.':status:'.$status;
    }

    protected function getLockKey(string $connection): string
    {
        return $this->prefix.$connection.':lock';
    }
}
