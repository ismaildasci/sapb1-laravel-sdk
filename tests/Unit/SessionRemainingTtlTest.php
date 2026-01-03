<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use SapB1\Session\SessionData;

describe('SessionData getRemainingTtl', function (): void {
    it('returns positive TTL for valid session', function (): void {
        $session = new SessionData(
            sessionId: 'test-session',
            routeId: 'test-route',
            companyDb: 'SBOTEST',
            expiresAt: CarbonImmutable::now()->addMinutes(10),
            createdAt: CarbonImmutable::now()
        );

        $ttl = $session->getRemainingTtl();

        expect($ttl)->toBeGreaterThan(500);
        expect($ttl)->toBeLessThanOrEqual(600);
    });

    it('returns zero for expired session', function (): void {
        $session = new SessionData(
            sessionId: 'test-session',
            routeId: 'test-route',
            companyDb: 'SBOTEST',
            expiresAt: CarbonImmutable::now()->subMinutes(5),
            createdAt: CarbonImmutable::now()->subMinutes(35)
        );

        expect($session->getRemainingTtl())->toBe(0);
    });

    it('returns correct TTL near expiry', function (): void {
        $session = new SessionData(
            sessionId: 'test-session',
            routeId: 'test-route',
            companyDb: 'SBOTEST',
            expiresAt: CarbonImmutable::now()->addSeconds(30),
            createdAt: CarbonImmutable::now()->subMinutes(27)
        );

        $ttl = $session->getRemainingTtl();

        expect($ttl)->toBeGreaterThan(25);
        expect($ttl)->toBeLessThanOrEqual(30);
    });
});
