<?php

declare(strict_types=1);

namespace SapB1\Session\Pool\Algorithms;

use SapB1\Session\Pool\PooledSession;

/**
 * Last-In-First-Out (LIFO) distribution algorithm.
 *
 * Selects the most recently released session, which can improve
 * cache locality and keep fewer sessions warm.
 */
class LifoAlgorithm implements DistributionAlgorithm
{
    public function select(array $sessions): ?PooledSession
    {
        if (empty($sessions)) {
            return null;
        }

        // Sort by released_at (newest first) for LIFO effect
        usort($sessions, function (PooledSession $a, PooledSession $b): int {
            $aTime = $a->releasedAt !== null ? $a->releasedAt->timestamp : 0;
            $bTime = $b->releasedAt !== null ? $b->releasedAt->timestamp : 0;

            return $bTime <=> $aTime;
        });

        return $sessions[0];
    }

    public function getName(): string
    {
        return 'lifo';
    }
}
