<?php

declare(strict_types=1);

namespace SapB1\Contracts;

use SapB1\Session\SessionData;

/**
 * Interface for session pool implementations.
 *
 * Session pools manage multiple SAP B1 sessions for high-concurrency scenarios.
 * They provide automatic session acquisition, release, and lifecycle management.
 */
interface SessionPoolInterface
{
    /**
     * Acquire a session from the pool.
     *
     * This method blocks until a session is available or timeout is reached.
     *
     * @param  string  $connection  The connection name
     * @param  int  $timeout  Maximum time to wait in seconds (0 = no wait)
     * @return SessionData|null The acquired session, or null if timeout
     */
    public function acquire(string $connection, int $timeout = 30): ?SessionData;

    /**
     * Release a session back to the pool.
     *
     * @param  string  $connection  The connection name
     * @param  SessionData  $session  The session to release
     * @param  bool  $invalidate  Whether to invalidate the session instead of returning it
     */
    public function release(string $connection, SessionData $session, bool $invalidate = false): void;

    /**
     * Get the current pool size for a connection.
     *
     * @param  string  $connection  The connection name
     * @return int Total number of sessions (active + idle)
     */
    public function size(string $connection): int;

    /**
     * Get the number of available (idle) sessions.
     *
     * @param  string  $connection  The connection name
     * @return int Number of idle sessions
     */
    public function available(string $connection): int;

    /**
     * Get the number of active (in-use) sessions.
     *
     * @param  string  $connection  The connection name
     * @return int Number of active sessions
     */
    public function active(string $connection): int;

    /**
     * Get pool statistics.
     *
     * @param  string  $connection  The connection name
     * @return array{
     *     total: int,
     *     active: int,
     *     idle: int,
     *     expired: int,
     *     waiting: int,
     *     min_size: int,
     *     max_size: int,
     *     algorithm: string
     * }
     */
    public function stats(string $connection): array;

    /**
     * Warm up the pool by pre-creating sessions.
     *
     * @param  string  $connection  The connection name
     * @param  int|null  $count  Number of sessions to create (null = min_size)
     * @return int Number of sessions created
     */
    public function warmUp(string $connection, ?int $count = null): int;

    /**
     * Drain the pool by closing all sessions.
     *
     * @param  string  $connection  The connection name
     * @return int Number of sessions closed
     */
    public function drain(string $connection): int;

    /**
     * Clean up expired and stale sessions.
     *
     * @param  string  $connection  The connection name
     * @return int Number of sessions cleaned up
     */
    public function cleanup(string $connection): int;
}
