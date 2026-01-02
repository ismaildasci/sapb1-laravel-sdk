<?php

declare(strict_types=1);

namespace SapB1\Events;

use Illuminate\Foundation\Events\Dispatchable;

readonly class RequestSending
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $connection,
        public string $method,
        public string $endpoint,
        public array $options = []
    ) {}
}
