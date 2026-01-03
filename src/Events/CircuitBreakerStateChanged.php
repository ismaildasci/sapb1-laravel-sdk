<?php

declare(strict_types=1);

namespace SapB1\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Event dispatched when circuit breaker state changes.
 *
 * This event is fired when the circuit breaker transitions between states:
 * - CLOSED -> OPEN (failure threshold reached)
 * - OPEN -> HALF_OPEN (open duration expired)
 * - HALF_OPEN -> CLOSED (successful recovery)
 * - HALF_OPEN -> OPEN (failure during recovery)
 */
readonly class CircuitBreakerStateChanged
{
    use Dispatchable;

    public function __construct(
        public string $endpoint,
        public string $previousState,
        public string $newState,
        public int $failureCount
    ) {}
}
