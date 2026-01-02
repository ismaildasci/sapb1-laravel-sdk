<?php

declare(strict_types=1);

namespace SapB1\Events;

use Illuminate\Foundation\Events\Dispatchable;

readonly class RequestSent
{
    use Dispatchable;

    public function __construct(
        public string $connection,
        public string $method,
        public string $endpoint,
        public int $statusCode,
        public float $duration
    ) {}
}
