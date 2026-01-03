<?php

declare(strict_types=1);

namespace SapB1\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event dispatched when a pooled session expires or is invalidated.
 */
class PoolSessionExpired
{
    use Dispatchable;

    public function __construct(
        public readonly string $connection,
        public readonly string $sessionId
    ) {}
}
