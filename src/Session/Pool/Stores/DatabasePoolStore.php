<?php

declare(strict_types=1);

namespace SapB1\Session\Pool\Stores;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use SapB1\Contracts\SessionPoolStoreInterface;
use SapB1\Session\Pool\Algorithms\DistributionAlgorithm;
use SapB1\Session\Pool\Algorithms\LeastConnectionsAlgorithm;
use SapB1\Session\Pool\Algorithms\LifoAlgorithm;
use SapB1\Session\Pool\Algorithms\RoundRobinAlgorithm;
use SapB1\Session\Pool\PooledSession;

/**
 * Database-based session pool storage.
 *
 * Uses a dedicated table for storing multiple sessions per connection
 * with atomic operations for concurrent access.
 */
class DatabasePoolStore implements SessionPoolStoreInterface
{
    protected string $table = 'sap_b1_session_pool';

    /**
     * @var array<string, DistributionAlgorithm>
     */
    protected array $algorithms = [];

    public function __construct(
        protected DatabaseManager $database,
        protected ?string $connection = null
    ) {
        $this->algorithms = [
            'round_robin' => new RoundRobinAlgorithm,
            'least_connections' => new LeastConnectionsAlgorithm,
            'lifo' => new LifoAlgorithm,
        ];
    }

    public function store(string $connection, PooledSession $session): void
    {
        $this->table()->insert([
            'connection' => $connection,
            'session_id' => $session->getSessionId(),
            'payload' => json_encode($session->session->toArray()),
            'status' => $session->status,
            'acquired_at' => $session->acquiredAt?->toDateTimeString(),
            'released_at' => $session->releasedAt?->toDateTimeString(),
            'expires_at' => $session->session->expiresAt->toDateTimeString(),
            'use_count' => $session->useCount,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    public function remove(string $connection, string $sessionId): void
    {
        $this->table()
            ->where('connection', $connection)
            ->where('session_id', $sessionId)
            ->delete();
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

        // Atomically update to active status
        $affected = $this->table()
            ->where('connection', $connection)
            ->where('session_id', $selected->getSessionId())
            ->where('status', PooledSession::STATUS_IDLE)
            ->update([
                'status' => PooledSession::STATUS_ACTIVE,
                'acquired_at' => now()->toDateTimeString(),
                'use_count' => $selected->useCount + 1,
                'updated_at' => now()->toDateTimeString(),
            ]);

        if ($affected === 0) {
            // Session was acquired by another process, try again
            return $this->acquireNext($connection, $algorithm);
        }

        return $selected->withAcquired();
    }

    public function release(string $connection, string $sessionId): void
    {
        $this->table()
            ->where('connection', $connection)
            ->where('session_id', $sessionId)
            ->update([
                'status' => PooledSession::STATUS_IDLE,
                'released_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);
    }

    public function markExpired(string $connection, string $sessionId): void
    {
        $this->table()
            ->where('connection', $connection)
            ->where('session_id', $sessionId)
            ->update([
                'status' => PooledSession::STATUS_EXPIRED,
                'updated_at' => now()->toDateTimeString(),
            ]);
    }

    public function getAll(string $connection): array
    {
        return $this->table()
            ->where('connection', $connection)
            ->get()
            ->map(fn ($record) => $this->recordToPooledSession($record))
            ->all();
    }

    public function getIdle(string $connection): array
    {
        return $this->table()
            ->where('connection', $connection)
            ->where('status', PooledSession::STATUS_IDLE)
            ->get()
            ->map(fn ($record) => $this->recordToPooledSession($record))
            ->all();
    }

    public function getActive(string $connection): array
    {
        return $this->table()
            ->where('connection', $connection)
            ->where('status', PooledSession::STATUS_ACTIVE)
            ->get()
            ->map(fn ($record) => $this->recordToPooledSession($record))
            ->all();
    }

    public function get(string $connection, string $sessionId): ?PooledSession
    {
        $record = $this->table()
            ->where('connection', $connection)
            ->where('session_id', $sessionId)
            ->first();

        if ($record === null) {
            return null;
        }

        return $this->recordToPooledSession($record);
    }

    public function update(string $connection, PooledSession $session): void
    {
        $this->table()
            ->where('connection', $connection)
            ->where('session_id', $session->getSessionId())
            ->update([
                'payload' => json_encode($session->session->toArray()),
                'status' => $session->status,
                'acquired_at' => $session->acquiredAt?->toDateTimeString(),
                'released_at' => $session->releasedAt?->toDateTimeString(),
                'expires_at' => $session->session->expiresAt->toDateTimeString(),
                'use_count' => $session->useCount,
                'updated_at' => now()->toDateTimeString(),
            ]);
    }

    public function count(string $connection): int
    {
        return $this->table()
            ->where('connection', $connection)
            ->count();
    }

    public function countByStatus(string $connection, string $status): int
    {
        return $this->table()
            ->where('connection', $connection)
            ->where('status', $status)
            ->count();
    }

    public function removeExpired(string $connection): int
    {
        // Remove sessions that are past their expires_at
        $deleted = $this->table()
            ->where('connection', $connection)
            ->where('expires_at', '<', now()->toDateTimeString())
            ->delete();

        // Also remove sessions marked as expired
        $deleted += $this->table()
            ->where('connection', $connection)
            ->where('status', PooledSession::STATUS_EXPIRED)
            ->delete();

        return $deleted;
    }

    public function removeAll(string $connection): int
    {
        return $this->table()
            ->where('connection', $connection)
            ->delete();
    }

    public function acquireLock(string $connection, int $timeout = 10): bool
    {
        $lockKey = "pool_lock:{$connection}";

        // Use the main sessions table for locking (same pattern as DatabaseSessionDriver)
        $existingLock = $this->getDbConnection()
            ->table('sap_b1_sessions')
            ->where('connection', $lockKey)
            ->first();

        if ($existingLock !== null) {
            $lockTime = strtotime($existingLock->updated_at);

            if (time() - $lockTime < $timeout) {
                return false;
            }
        }

        $this->getDbConnection()
            ->table('sap_b1_sessions')
            ->updateOrInsert(
                ['connection' => $lockKey],
                [
                    'payload' => '{"locked":true}',
                    'expires_at' => now()->addSeconds($timeout)->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ]
            );

        return true;
    }

    public function releaseLock(string $connection): void
    {
        $lockKey = "pool_lock:{$connection}";

        $this->getDbConnection()
            ->table('sap_b1_sessions')
            ->where('connection', $lockKey)
            ->delete();
    }

    /**
     * Convert a database record to a PooledSession.
     */
    protected function recordToPooledSession(\stdClass $record): PooledSession
    {
        /** @var string $payloadString */
        $payloadString = $record->payload ?? '{}';

        /** @var array<string, mixed> $payload */
        $payload = json_decode($payloadString, true) ?? [];

        return PooledSession::fromArray([
            'session' => $payload,
            'status' => (string) ($record->status ?? PooledSession::STATUS_IDLE),
            'acquired_at' => $record->acquired_at ?? null,
            'released_at' => $record->released_at ?? null,
            'use_count' => (int) ($record->use_count ?? 0),
            'pool_created_at' => $record->created_at ?? null,
        ]);
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function table()
    {
        return $this->getDbConnection()->table($this->table);
    }

    protected function getDbConnection(): ConnectionInterface
    {
        return $this->database->connection($this->connection);
    }
}
