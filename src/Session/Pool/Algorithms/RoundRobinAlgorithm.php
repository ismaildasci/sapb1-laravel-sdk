<?php

declare(strict_types=1);

namespace SapB1\Session\Pool\Algorithms;

use SapB1\Session\Pool\PooledSession;

/**
 * Round-robin distribution algorithm.
 *
 * Selects sessions in sequential order, distributing load evenly
 * across all available sessions. Uses release time to determine order.
 */
class RoundRobinAlgorithm implements DistributionAlgorithm
{
    public function select(array $sessions): ?PooledSession
    {
        if (empty($sessions)) {
            return null;
        }

        // Sort by released_at (oldest first) for round-robin effect
        usort($sessions, function (PooledSession $a, PooledSession $b): int {
            $aTime = $a->releasedAt !== null ? $a->releasedAt->timestamp : 0;
            $bTime = $b->releasedAt !== null ? $b->releasedAt->timestamp : 0;

            return $aTime <=> $bTime;
        });

        return $sessions[0];
    }

    public function getName(): string
    {
        return 'round_robin';
    }
}
