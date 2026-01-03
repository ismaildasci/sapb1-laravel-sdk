<?php

declare(strict_types=1);

namespace SapB1\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event dispatched when a session is acquired from the pool.
 */
class SessionAcquired
{
    use Dispatchable;

    public function __construct(
        public readonly string $connection,
        public readonly string $sessionId
    ) {}
}
