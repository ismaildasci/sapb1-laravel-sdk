<?php

declare(strict_types=1);

namespace SapB1\Session\Pool\Algorithms;

use SapB1\Session\Pool\PooledSession;

/**
 * Least connections distribution algorithm.
 *
 * Selects the session with the least usage count, distributing load
 * to less-used sessions for better balance over time.
 */
class LeastConnectionsAlgorithm implements DistributionAlgorithm
{
    public function select(array $sessions): ?PooledSession
    {
        if (empty($sessions)) {
            return null;
        }

        // Sort by use_count (lowest first)
        usort($sessions, function (PooledSession $a, PooledSession $b): int {
            return $a->useCount <=> $b->useCount;
        });

        return $sessions[0];
    }

    public function getName(): string
    {
        return 'least_connections';
    }
}
