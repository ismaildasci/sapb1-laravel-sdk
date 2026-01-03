<?php

declare(strict_types=1);

use SapB1\Session\SessionManager;

describe('SessionManager Session Error Detection', function (): void {
    it('detects session error by error code 301', function (): void {
        $sessionManager = app(SessionManager::class);

        $response = [
            'error' => [
                'code' => 301,
                'message' => ['value' => 'Invalid session'],
            ],
        ];

        expect($sessionManager->isSessionError($response))->toBeTrue();
    });

    it('detects session error by error code -301', function (): void {
        $sessionManager = app(SessionManager::class);

        $response = [
            'error' => [
                'code' => -301,
                'message' => 'Session expired',
            ],
        ];

        expect($sessionManager->isSessionError($response))->toBeTrue();
    });

    it('detects session error by message containing session keyword', function (): void {
        $sessionManager = app(SessionManager::class);

        $response = [
            'error' => [
                'code' => 500,
                'message' => ['value' => 'Invalid session token'],
            ],
        ];

        expect($sessionManager->isSessionError($response))->toBeTrue();
    });

    it('detects session error by message containing login keyword', function (): void {
        $sessionManager = app(SessionManager::class);

        $response = [
            'error' => [
                'code' => 401,
                'message' => 'Please login again',
            ],
        ];

        expect($sessionManager->isSessionError($response))->toBeTrue();
    });

    it('returns false for non-session errors', function (): void {
        $sessionManager = app(SessionManager::class);

        $response = [
            'error' => [
                'code' => 500,
                'message' => 'Internal server error',
            ],
        ];

        expect($sessionManager->isSessionError($response))->toBeFalse();
    });

    it('returns false for null response', function (): void {
        $sessionManager = app(SessionManager::class);

        expect($sessionManager->isSessionError(null))->toBeFalse();
    });

    it('returns false for empty response', function (): void {
        $sessionManager = app(SessionManager::class);

        expect($sessionManager->isSessionError([]))->toBeFalse();
    });
});
