<?php

declare(strict_types=1);

namespace SapB1\Contracts;

use SapB1\Session\SessionData;

interface SessionStoreInterface
{
    /**
     * Get session data for the given connection.
     */
    public function get(string $connection): ?SessionData;

    /**
     * Store session data for the given connection.
     */
    public function put(string $connection, SessionData $session): void;

    /**
     * Remove session data for the given connection.
     */
    public function forget(string $connection): void;

    /**
     * Check if session exists for the given connection.
     */
    public function has(string $connection): bool;

    /**
     * Check if session needs to be refreshed.
     */
    public function needsRefresh(string $connection): bool;

    /**
     * Acquire a lock for session refresh.
     */
    public function acquireLock(string $connection, int $seconds = 10): bool;

    /**
     * Release the session refresh lock.
     */
    public function releaseLock(string $connection): void;

    /**
     * Remove all stored sessions.
     */
    public function flush(): void;
}
