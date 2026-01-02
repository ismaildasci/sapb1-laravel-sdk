<?php

declare(strict_types=1);

namespace SapB1\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

readonly class RequestFailed
{
    use Dispatchable;

    public function __construct(
        public string $connection,
        public string $method,
        public string $endpoint,
        public Throwable $exception
    ) {}
}
