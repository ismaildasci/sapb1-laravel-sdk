<?php

declare(strict_types=1);

namespace SapB1\Session\Drivers;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use SapB1\Contracts\SessionStoreInterface;
use SapB1\Session\SessionData;

class DatabaseSessionDriver implements SessionStoreInterface
{
    protected string $table = 'sap_b1_sessions';

    protected int $refreshThreshold;

    public function __construct(
        protected DatabaseManager $database,
        protected ?string $connection = null
    ) {
        $this->refreshThreshold = (int) config('sap-b1.session.refresh_threshold', 300);
    }

    public function get(string $connection): ?SessionData
    {
        $record = $this->table()
            ->where('connection', $connection)
            ->first();

        if ($record === null) {
            return null;
        }

        $session = SessionData::fromJson($record->payload);

        if ($session->isExpired()) {
            $this->forget($connection);

            return null;
        }

        return $session;
    }

    public function put(string $connection, SessionData $session): void
    {
        $this->table()->updateOrInsert(
            ['connection' => $connection],
            [
                'payload' => $session->toJson(),
                'expires_at' => $session->expiresAt->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]
        );
    }

    public function forget(string $connection): void
    {
        $this->table()
            ->where('connection', $connection)
            ->delete();
    }

    public function has(string $connection): bool
    {
        return $this->table()
            ->where('connection', $connection)
            ->exists();
    }

    public function needsRefresh(string $connection): bool
    {
        $session = $this->get($connection);

        if ($session === null) {
            return true;
        }

        return $session->isNearExpiry($this->refreshThreshold);
    }

    public function acquireLock(string $connection, int $seconds = 10): bool
    {
        $lockKey = $connection.'_lock';

        $existingLock = $this->table()
            ->where('connection', $lockKey)
            ->first();

        if ($existingLock !== null) {
            $lockTime = strtotime($existingLock->updated_at);

            if (time() - $lockTime < $seconds) {
                return false;
            }
        }

        $this->table()->updateOrInsert(
            ['connection' => $lockKey],
            [
                'payload' => '{"locked":true}',
                'expires_at' => now()->addSeconds($seconds)->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]
        );

        return true;
    }

    public function releaseLock(string $connection): void
    {
        $this->table()
            ->where('connection', $connection.'_lock')
            ->delete();
    }

    public function flush(): void
    {
        $this->table()->truncate();
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function table()
    {
        return $this->getConnection()->table($this->table);
    }

    protected function getConnection(): ConnectionInterface
    {
        return $this->database->connection($this->connection);
    }
}
