<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use SapB1\Session\Pool\Algorithms\LeastConnectionsAlgorithm;
use SapB1\Session\Pool\Algorithms\LifoAlgorithm;
use SapB1\Session\Pool\Algorithms\RoundRobinAlgorithm;
use SapB1\Session\Pool\PooledSession;
use SapB1\Session\SessionData;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2024-01-15 10:00:00');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

function createPooledSession(string $id, int $useCount = 0, ?CarbonImmutable $releasedAt = null): PooledSession
{
    $session = new SessionData(
        sessionId: $id,
        routeId: 'server:50000',
        companyDb: 'TESTDB',
        expiresAt: CarbonImmutable::now()->addHour(),
        createdAt: CarbonImmutable::now()
    );

    return new PooledSession(
        session: $session,
        status: PooledSession::STATUS_IDLE,
        acquiredAt: null,
        releasedAt: $releasedAt,
        useCount: $useCount,
        createdAt: CarbonImmutable::now()
    );
}

describe('RoundRobinAlgorithm', function (): void {
    it('returns null for empty array', function (): void {
        $algorithm = new RoundRobinAlgorithm;

        expect($algorithm->select([]))->toBeNull();
    });

    it('returns the only session when single session', function (): void {
        $algorithm = new RoundRobinAlgorithm;
        $session = createPooledSession('session-1');

        $selected = $algorithm->select([$session]);

        expect($selected)->toBe($session);
    });

    it('selects oldest released session first', function (): void {
        $algorithm = new RoundRobinAlgorithm;

        $oldest = createPooledSession('oldest', 1, CarbonImmutable::parse('2024-01-15T09:00:00Z'));
        $middle = createPooledSession('middle', 1, CarbonImmutable::parse('2024-01-15T09:30:00Z'));
        $newest = createPooledSession('newest', 1, CarbonImmutable::parse('2024-01-15T09:55:00Z'));

        $selected = $algorithm->select([$newest, $oldest, $middle]);

        expect($selected->getSessionId())->toBe('oldest');
    });

    it('handles null released_at values', function (): void {
        $algorithm = new RoundRobinAlgorithm;

        $withRelease = createPooledSession('with-release', 1, CarbonImmutable::parse('2024-01-15T09:30:00Z'));
        $withoutRelease = createPooledSession('without-release', 0, null);

        $selected = $algorithm->select([$withRelease, $withoutRelease]);

        // null releasedAt treated as timestamp 0 (oldest)
        expect($selected->getSessionId())->toBe('without-release');
    });

    it('returns correct name', function (): void {
        $algorithm = new RoundRobinAlgorithm;

        expect($algorithm->getName())->toBe('round_robin');
    });
});

describe('LeastConnectionsAlgorithm', function (): void {
    it('returns null for empty array', function (): void {
        $algorithm = new LeastConnectionsAlgorithm;

        expect($algorithm->select([]))->toBeNull();
    });

    it('returns the only session when single session', function (): void {
        $algorithm = new LeastConnectionsAlgorithm;
        $session = createPooledSession('session-1', 5);

        $selected = $algorithm->select([$session]);

        expect($selected)->toBe($session);
    });

    it('selects least used session', function (): void {
        $algorithm = new LeastConnectionsAlgorithm;

        $heavy = createPooledSession('heavy', 100);
        $medium = createPooledSession('medium', 50);
        $light = createPooledSession('light', 10);

        $selected = $algorithm->select([$heavy, $medium, $light]);

        expect($selected->getSessionId())->toBe('light');
    });

    it('selects first when use counts are equal', function (): void {
        $algorithm = new LeastConnectionsAlgorithm;

        $first = createPooledSession('first', 5);
        $second = createPooledSession('second', 5);
        $third = createPooledSession('third', 5);

        $selected = $algorithm->select([$first, $second, $third]);

        expect($selected->getSessionId())->toBe('first');
    });

    it('returns correct name', function (): void {
        $algorithm = new LeastConnectionsAlgorithm;

        expect($algorithm->getName())->toBe('least_connections');
    });
});

describe('LifoAlgorithm', function (): void {
    it('returns null for empty array', function (): void {
        $algorithm = new LifoAlgorithm;

        expect($algorithm->select([]))->toBeNull();
    });

    it('returns the only session when single session', function (): void {
        $algorithm = new LifoAlgorithm;
        $session = createPooledSession('session-1');

        $selected = $algorithm->select([$session]);

        expect($selected)->toBe($session);
    });

    it('selects most recently released session first', function (): void {
        $algorithm = new LifoAlgorithm;

        $oldest = createPooledSession('oldest', 1, CarbonImmutable::parse('2024-01-15T09:00:00Z'));
        $middle = createPooledSession('middle', 1, CarbonImmutable::parse('2024-01-15T09:30:00Z'));
        $newest = createPooledSession('newest', 1, CarbonImmutable::parse('2024-01-15T09:55:00Z'));

        $selected = $algorithm->select([$oldest, $middle, $newest]);

        expect($selected->getSessionId())->toBe('newest');
    });

    it('handles null released_at values', function (): void {
        $algorithm = new LifoAlgorithm;

        $withRelease = createPooledSession('with-release', 1, CarbonImmutable::parse('2024-01-15T09:30:00Z'));
        $withoutRelease = createPooledSession('without-release', 0, null);

        $selected = $algorithm->select([$withRelease, $withoutRelease]);

        // null releasedAt treated as timestamp 0, so session with release time is more recent
        expect($selected->getSessionId())->toBe('with-release');
    });

    it('returns correct name', function (): void {
        $algorithm = new LifoAlgorithm;

        expect($algorithm->getName())->toBe('lifo');
    });
});
