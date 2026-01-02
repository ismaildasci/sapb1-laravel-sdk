<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use SapB1\Session\SessionData;

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2024-01-15 10:00:00');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

describe('SessionData', function (): void {
    it('can be created from login response', function (): void {
        $response = [
            'SessionId' => 'abc123',
            'RouteId' => 'server:50000',
        ];

        $session = SessionData::fromLoginResponse($response, 'TESTDB', 1800);

        expect($session->sessionId)->toBe('abc123')
            ->and($session->routeId)->toBe('server:50000')
            ->and($session->companyDb)->toBe('TESTDB')
            ->and((int) $session->createdAt->diffInSeconds($session->expiresAt))->toBe(1800);
    });

    it('can be created from array with snake_case keys', function (): void {
        $data = [
            'session_id' => 'test-session',
            'route_id' => 'server:50000',
            'company_db' => 'TESTDB',
            'expires_at' => '2024-01-15T10:30:00+00:00',
            'created_at' => '2024-01-15T10:00:00+00:00',
        ];

        $session = SessionData::fromArray($data);

        expect($session->sessionId)->toBe('test-session')
            ->and($session->routeId)->toBe('server:50000')
            ->and($session->companyDb)->toBe('TESTDB');
    });

    it('can be created from array with camelCase keys', function (): void {
        $data = [
            'sessionId' => 'test-session',
            'routeId' => 'server:50000',
            'companyDb' => 'TESTDB',
            'expiresAt' => '2024-01-15T10:30:00+00:00',
            'createdAt' => '2024-01-15T10:00:00+00:00',
        ];

        $session = SessionData::fromArray($data);

        expect($session->sessionId)->toBe('test-session')
            ->and($session->routeId)->toBe('server:50000')
            ->and($session->companyDb)->toBe('TESTDB');
    });

    it('can be serialized to json', function (): void {
        $session = new SessionData(
            sessionId: 'test-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::parse('2024-01-15T10:30:00Z'),
            createdAt: CarbonImmutable::parse('2024-01-15T10:00:00Z')
        );

        $json = $session->toJson();
        $decoded = json_decode($json, true);

        expect($decoded['session_id'])->toBe('test-session')
            ->and($decoded['company_db'])->toBe('TESTDB');
    });

    it('can be created from json', function (): void {
        $json = json_encode([
            'session_id' => 'json-session',
            'route_id' => 'server:50000',
            'company_db' => 'JSONDB',
            'expires_at' => '2024-01-15T10:30:00+00:00',
            'created_at' => '2024-01-15T10:00:00+00:00',
        ]);

        $session = SessionData::fromJson($json);

        expect($session->sessionId)->toBe('json-session')
            ->and($session->companyDb)->toBe('JSONDB');
    });

    it('detects expired session', function (): void {
        $session = new SessionData(
            sessionId: 'expired-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::parse('2024-01-15T09:00:00Z'), // Past
            createdAt: CarbonImmutable::parse('2024-01-15T08:30:00Z')
        );

        expect($session->isExpired())->toBeTrue();
    });

    it('detects valid session', function (): void {
        $session = new SessionData(
            sessionId: 'valid-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::parse('2024-01-15T11:00:00Z'), // Future
            createdAt: CarbonImmutable::parse('2024-01-15T10:00:00Z')
        );

        expect($session->isExpired())->toBeFalse();
    });

    it('detects session near expiry', function (): void {
        $session = new SessionData(
            sessionId: 'near-expiry-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::parse('2024-01-15T10:04:00Z'), // 4 minutes from now
            createdAt: CarbonImmutable::parse('2024-01-15T09:30:00Z')
        );

        expect($session->isNearExpiry(300))->toBeTrue(); // 5 min threshold
    });

    it('detects session not near expiry', function (): void {
        $session = new SessionData(
            sessionId: 'not-near-expiry',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::parse('2024-01-15T10:30:00Z'), // 30 minutes from now
            createdAt: CarbonImmutable::parse('2024-01-15T10:00:00Z')
        );

        expect($session->isNearExpiry(300))->toBeFalse();
    });

    it('can refresh session with new expiry', function (): void {
        $session = new SessionData(
            sessionId: 'old-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::parse('2024-01-15T10:05:00Z'),
            createdAt: CarbonImmutable::parse('2024-01-15T09:35:00Z')
        );

        $refreshed = $session->refresh(1800);

        expect($refreshed->sessionId)->toBe('old-session')
            ->and($refreshed->expiresAt->gt($session->expiresAt))->toBeTrue()
            ->and($refreshed)->not->toBe($session); // Immutable
    });

    it('returns correct headers', function (): void {
        $session = new SessionData(
            sessionId: 'test-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::now()->addHour(),
            createdAt: CarbonImmutable::now()
        );

        $headers = $session->getHeaders();

        expect($headers)->toHaveKey('Cookie')
            ->and($headers['Cookie'])->toContain('B1SESSION=test-session')
            ->and($headers['Cookie'])->toContain('ROUTEID=server:50000');
    });

    it('converts to array with snake_case keys', function (): void {
        $session = new SessionData(
            sessionId: 'test-session',
            routeId: 'server:50000',
            companyDb: 'TESTDB',
            expiresAt: CarbonImmutable::parse('2024-01-15T10:30:00Z'),
            createdAt: CarbonImmutable::parse('2024-01-15T10:00:00Z')
        );

        $array = $session->toArray();

        expect($array)->toHaveKey('session_id')
            ->and($array)->toHaveKey('route_id')
            ->and($array)->toHaveKey('company_db')
            ->and($array)->toHaveKey('expires_at')
            ->and($array)->toHaveKey('created_at');
    });
});
