<?php

declare(strict_types=1);

namespace SapB1\Contracts;

use SapB1\Session\Pool\PooledSession;

/**
 * Interface for session pool storage backends.
 *
 * Pool stores manage the persistence of multiple sessions per connection,
 * supporting atomic acquire/release operations for high-concurrency scenarios.
 */
interface SessionPoolStoreInterface
{
    /**
     * Store a session in the pool.
     */
    public function store(string $connection, PooledSession $session): void;

    /**
     * Remove a session from the pool.
     */
    public function remove(string $connection, string $sessionId): void;

    /**
     * Atomically acquire the next available session.
     *
     * @param  string  $connection  The connection name
     * @param  string  $algorithm  The distribution algorithm to use
     * @return PooledSession|null The acquired session with ACTIVE status, or null if none available
     */
    public function acquireNext(string $connection, string $algorithm): ?PooledSession;

    /**
     * Release a session back to idle status.
     */
    public function release(string $connection, string $sessionId): void;

    /**
     * Mark a session as expired.
     */
    public function markExpired(string $connection, string $sessionId): void;

    /**
     * Get all sessions for a connection.
     *
     * @return array<PooledSession>
     */
    public function getAll(string $connection): array;

    /**
     * Get all idle sessions for a connection.
     *
     * @return array<PooledSession>
     */
    public function getIdle(string $connection): array;

    /**
     * Get all active sessions for a connection.
     *
     * @return array<PooledSession>
     */
    public function getActive(string $connection): array;

    /**
     * Get a specific session by ID.
     */
    public function get(string $connection, string $sessionId): ?PooledSession;

    /**
     * Update an existing session.
     */
    public function update(string $connection, PooledSession $session): void;

    /**
     * Count total sessions for a connection.
     */
    public function count(string $connection): int;

    /**
     * Count sessions by status for a connection.
     */
    public function countByStatus(string $connection, string $status): int;

    /**
     * Remove expired sessions.
     *
     * @return int Number of sessions removed
     */
    public function removeExpired(string $connection): int;

    /**
     * Remove all sessions for a connection.
     *
     * @return int Number of sessions removed
     */
    public function removeAll(string $connection): int;

    /**
     * Acquire a lock for the connection pool.
     */
    public function acquireLock(string $connection, int $timeout = 10): bool;

    /**
     * Release the lock for the connection pool.
     */
    public function releaseLock(string $connection): void;
}
