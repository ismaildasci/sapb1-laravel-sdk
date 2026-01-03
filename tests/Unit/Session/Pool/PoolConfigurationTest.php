<?php

declare(strict_types=1);

use SapB1\Exceptions\PoolConfigurationException;
use SapB1\Session\Pool\PoolConfiguration;

describe('PoolConfiguration', function (): void {
    it('creates with default values', function (): void {
        $config = new PoolConfiguration;

        expect($config->minSize)->toBe(2)
            ->and($config->maxSize)->toBe(10)
            ->and($config->idleTimeout)->toBe(600)
            ->and($config->waitTimeout)->toBe(30)
            ->and($config->algorithm)->toBe('round_robin')
            ->and($config->validationOnAcquire)->toBeTrue()
            ->and($config->warmupOnBoot)->toBeTrue()
            ->and($config->cleanupInterval)->toBe(60);
    });

    it('creates with custom values', function (): void {
        $config = new PoolConfiguration(
            minSize: 5,
            maxSize: 20,
            idleTimeout: 300,
            waitTimeout: 60,
            algorithm: 'lifo',
            validationOnAcquire: false,
            warmupOnBoot: false,
            cleanupInterval: 120
        );

        expect($config->minSize)->toBe(5)
            ->and($config->maxSize)->toBe(20)
            ->and($config->idleTimeout)->toBe(300)
            ->and($config->waitTimeout)->toBe(60)
            ->and($config->algorithm)->toBe('lifo')
            ->and($config->validationOnAcquire)->toBeFalse()
            ->and($config->warmupOnBoot)->toBeFalse()
            ->and($config->cleanupInterval)->toBe(120);
    });

    it('validates min size is non-negative', function (): void {
        new PoolConfiguration(minSize: -1);
    })->throws(PoolConfigurationException::class);

    it('validates max size is at least 1', function (): void {
        new PoolConfiguration(maxSize: 0);
    })->throws(PoolConfigurationException::class);

    it('validates max size is greater than or equal to min size', function (): void {
        new PoolConfiguration(minSize: 10, maxSize: 5);
    })->throws(PoolConfigurationException::class);

    it('validates idle timeout is non-negative', function (): void {
        new PoolConfiguration(idleTimeout: -1);
    })->throws(PoolConfigurationException::class);

    it('validates wait timeout is non-negative', function (): void {
        new PoolConfiguration(waitTimeout: -1);
    })->throws(PoolConfigurationException::class);

    it('validates algorithm is supported', function (): void {
        new PoolConfiguration(algorithm: 'invalid_algorithm');
    })->throws(PoolConfigurationException::class);

    it('accepts valid round_robin algorithm', function (): void {
        $config = new PoolConfiguration(algorithm: 'round_robin');
        expect($config->algorithm)->toBe('round_robin');
    });

    it('accepts valid least_connections algorithm', function (): void {
        $config = new PoolConfiguration(algorithm: 'least_connections');
        expect($config->algorithm)->toBe('least_connections');
    });

    it('accepts valid lifo algorithm', function (): void {
        $config = new PoolConfiguration(algorithm: 'lifo');
        expect($config->algorithm)->toBe('lifo');
    });

    it('creates from connection config', function (): void {
        config([
            'sap-b1.pool.connections.test' => [
                'min_size' => 3,
                'max_size' => 15,
                'idle_timeout' => 400,
                'wait_timeout' => 45,
            ],
            'sap-b1.pool.algorithm' => 'least_connections',
            'sap-b1.pool.validation_on_acquire' => true,
            'sap-b1.pool.warmup_on_boot' => false,
            'sap-b1.pool.cleanup_interval' => 90,
        ]);

        $config = PoolConfiguration::forConnection('test');

        expect($config->minSize)->toBe(3)
            ->and($config->maxSize)->toBe(15)
            ->and($config->idleTimeout)->toBe(400)
            ->and($config->waitTimeout)->toBe(45)
            ->and($config->algorithm)->toBe('least_connections')
            ->and($config->warmupOnBoot)->toBeFalse()
            ->and($config->cleanupInterval)->toBe(90);
    });

    it('uses defaults when config is missing', function (): void {
        config(['sap-b1.pool' => []]);

        $config = PoolConfiguration::forConnection('nonexistent');

        expect($config->minSize)->toBe(2)
            ->and($config->maxSize)->toBe(10)
            ->and($config->algorithm)->toBe('round_robin');
    });

    it('returns supported algorithms', function (): void {
        $algorithms = PoolConfiguration::supportedAlgorithms();

        expect($algorithms)->toBe(['round_robin', 'least_connections', 'lifo']);
    });

    it('allows min_size of zero', function (): void {
        $config = new PoolConfiguration(minSize: 0, maxSize: 5);
        expect($config->minSize)->toBe(0);
    });
});
