<?php

declare(strict_types=1);

namespace SapB1\Session\Drivers;

use Illuminate\Redis\RedisManager;
use SapB1\Contracts\SessionStoreInterface;
use SapB1\Session\SessionData;

class RedisSessionDriver implements SessionStoreInterface
{
    protected string $prefix;

    protected int $ttl;

    protected int $refreshThreshold;

    public function __construct(
        protected RedisManager $redis,
        protected string $redisConnection = 'default'
    ) {
        $this->prefix = config('sap-b1.session.prefix', 'sap_b1_session:');
        $this->ttl = (int) config('sap-b1.session.ttl', 1680);
        $this->refreshThreshold = (int) config('sap-b1.session.refresh_threshold', 300);
    }

    public function get(string $connection): ?SessionData
    {
        $data = $this->connection()->get($this->getKey($connection));

        if ($data === null) {
            return null;
        }

        $session = SessionData::fromJson((string) $data);

        if ($session->isExpired()) {
            $this->forget($connection);

            return null;
        }

        return $session;
    }

    public function put(string $connection, SessionData $session): void
    {
        $this->connection()->setex(
            $this->getKey($connection),
            $this->ttl,
            $session->toJson()
        );
    }

    public function forget(string $connection): void
    {
        $this->connection()->del($this->getKey($connection));
    }

    public function has(string $connection): bool
    {
        return (bool) $this->connection()->exists($this->getKey($connection));
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
        /** @var bool|null $result */
        $result = $this->connection()->command('set', [
            $this->getLockKey($connection),
            '1',
            'EX',
            $seconds,
            'NX',
        ]);

        return (bool) $result;
    }

    public function releaseLock(string $connection): void
    {
        $this->connection()->del($this->getLockKey($connection));
    }

    public function flush(): void
    {
        $keys = $this->connection()->keys($this->prefix.'*');

        if (! empty($keys)) {
            $this->connection()->del(...$keys);
        }
    }

    /**
     * @return \Illuminate\Redis\Connections\Connection
     */
    protected function connection()
    {
        return $this->redis->connection($this->redisConnection);
    }

    protected function getKey(string $connection): string
    {
        return $this->prefix.$connection;
    }

    protected function getLockKey(string $connection): string
    {
        return $this->prefix.$connection.':lock';
    }
}
