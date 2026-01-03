<?php

declare(strict_types=1);

namespace SapB1\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event dispatched when pool warmup is completed.
 */
class PoolWarmedUp
{
    use Dispatchable;

    public function __construct(
        public readonly string $connection,
        public readonly int $sessionsCreated
    ) {}
}
