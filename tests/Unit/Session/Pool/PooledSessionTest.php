<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use SapB1\Session\Pool\PooledSession;
use SapB1\Session\SessionData;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2024-01-15 10:00:00');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

describe('PooledSession', function (): void {
    it('can be created from session data', function (): void {
        $session = new SessionData(
            sessionId: 'test-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::now()->addHour(),
            createdAt: CarbonImmutable::now()
        );

        $pooled = PooledSession::fromSession($session);

        expect($pooled->session)->toBe($session)
            ->and($pooled->status)->toBe(PooledSession::STATUS_IDLE)
            ->and($pooled->useCount)->toBe(0)
            ->and($pooled->acquiredAt)->toBeNull()
            ->and($pooled->releasedAt)->toBeNull();
    });

    it('returns correct session id', function (): void {
        $session = new SessionData(
            sessionId: 'unique-session-123',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::now()->addHour(),
            createdAt: CarbonImmutable::now()
        );

        $pooled = PooledSession::fromSession($session);

        expect($pooled->getSessionId())->toBe('unique-session-123');
    });

    it('can be marked as acquired with withAcquired', function (): void {
        $session = new SessionData(
            sessionId: 'test-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::now()->addHour(),
            createdAt: CarbonImmutable::now()
        );

        $pooled = PooledSession::fromSession($session);
        $acquired = $pooled->withAcquired();

        expect($acquired->status)->toBe(PooledSession::STATUS_ACTIVE)
            ->and($acquired->acquiredAt)->not->toBeNull()
            ->and($acquired->useCount)->toBe(1);
    });

    it('increments use count on each acquire', function (): void {
        $session = new SessionData(
            sessionId: 'test-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::now()->addHour(),
            createdAt: CarbonImmutable::now()
        );

        $pooled = PooledSession::fromSession($session);
        $acquired1 = $pooled->withAcquired();
        $released1 = $acquired1->withReleased();
        $acquired2 = $released1->withAcquired();

        expect($acquired2->useCount)->toBe(2);
    });

    it('can be marked as released with withReleased', function (): void {
        $session = new SessionData(
            sessionId: 'test-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::now()->addHour(),
            createdAt: CarbonImmutable::now()
        );

        $pooled = PooledSession::fromSession($session);
        $acquired = $pooled->withAcquired();
        $released = $acquired->withReleased();

        expect($released->status)->toBe(PooledSession::STATUS_IDLE)
            ->and($released->releasedAt)->not->toBeNull();
    });

    it('can be marked as expired with withExpired', function (): void {
        $session = new SessionData(
            sessionId: 'test-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::now()->addHour(),
            createdAt: CarbonImmutable::now()
        );

        $pooled = PooledSession::fromSession($session);
        $expired = $pooled->withExpired();

        expect($expired->status)->toBe(PooledSession::STATUS_EXPIRED);
    });

    it('detects idle status', function (): void {
        $session = new SessionData(
            sessionId: 'test-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::now()->addHour(),
            createdAt: CarbonImmutable::now()
        );

        $pooled = PooledSession::fromSession($session);

        expect($pooled->isIdle())->toBeTrue()
            ->and($pooled->isActive())->toBeFalse()
            ->and($pooled->isExpired())->toBeFalse();
    });

    it('detects active status', function (): void {
        $session = new SessionData(
            sessionId: 'test-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::now()->addHour(),
            createdAt: CarbonImmutable::now()
        );

        $pooled = PooledSession::fromSession($session)->withAcquired();

        expect($pooled->isActive())->toBeTrue()
            ->and($pooled->isIdle())->toBeFalse();
    });

    it('detects expired status from session', function (): void {
        $session = new SessionData(
            sessionId: 'test-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::now()->subHour(), // Already expired
            createdAt: CarbonImmutable::now()->subHours(2)
        );

        $pooled = PooledSession::fromSession($session);

        expect($pooled->isExpired())->toBeTrue();
    });

    it('can be serialized to array', function (): void {
        $session = new SessionData(
            sessionId: 'test-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::parse('2024-01-15T11:00:00Z'),
            createdAt: CarbonImmutable::parse('2024-01-15T10:00:00Z')
        );

        $pooled = PooledSession::fromSession($session);
        $array = $pooled->toArray();

        expect($array)->toHaveKey('session')
            ->and($array)->toHaveKey('status')
            ->and($array)->toHaveKey('use_count')
            ->and($array['status'])->toBe(PooledSession::STATUS_IDLE)
            ->and($array['use_count'])->toBe(0);
    });

    it('can be created from array', function (): void {
        $array = [
            'session' => [
                'session_id' => 'test-session',
                'route_id' => 'server:50000',
                'company_db' => 'TESTDB',
                'expires_at' => '2024-01-15T11:00:00Z',
                'created_at' => '2024-01-15T10:00:00Z',
            ],
            'status' => PooledSession::STATUS_ACTIVE,
            'use_count' => 5,
            'acquired_at' => '2024-01-15T10:05:00Z',
            'released_at' => null,
            'pool_created_at' => '2024-01-15T10:00:00Z',
        ];

        $pooled = PooledSession::fromArray($array);

        expect($pooled->session->sessionId)->toBe('test-session')
            ->and($pooled->status)->toBe(PooledSession::STATUS_ACTIVE)
            ->and($pooled->useCount)->toBe(5)
            ->and($pooled->acquiredAt)->not->toBeNull();
    });

    it('has correct status constants', function (): void {
        expect(PooledSession::STATUS_IDLE)->toBe('idle')
            ->and(PooledSession::STATUS_ACTIVE)->toBe('active')
            ->and(PooledSession::STATUS_EXPIRED)->toBe('expired');
    });

    it('can refresh session with new expiry', function (): void {
        $session = new SessionData(
            sessionId: 'test-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::now()->addMinutes(5),
            createdAt: CarbonImmutable::now()
        );

        $pooled = PooledSession::fromSession($session);
        $refreshed = $pooled->withRefreshedSession(1800);

        expect($refreshed->session->sessionId)->toBe('test-session')
            ->and($refreshed->session->expiresAt->gt($pooled->session->expiresAt))->toBeTrue();
    });
});
