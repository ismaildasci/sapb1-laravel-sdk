<?php

declare(strict_types=1);

namespace SapB1\Session\Pool\Algorithms;

use SapB1\Session\Pool\PooledSession;

/**
 * Interface for session distribution algorithms.
 *
 * Distribution algorithms determine which idle session to acquire next
 * from the pool, enabling different load balancing strategies.
 */
interface DistributionAlgorithm
{
    /**
     * Select the next session from available pool sessions.
     *
     * @param  array<PooledSession>  $sessions  Available idle sessions
     * @return PooledSession|null Selected session or null if none available
     */
    public function select(array $sessions): ?PooledSession;

    /**
     * Get the algorithm name.
     */
    public function getName(): string;
}
