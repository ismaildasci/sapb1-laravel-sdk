<?php

declare(strict_types=1);

namespace SapB1\Session\Pool;

use SapB1\Exceptions\PoolConfigurationException;

/**
 * Pool configuration value object.
 *
 * Encapsulates all configuration for a session pool with validation.
 */
readonly class PoolConfiguration
{
    public function __construct(
        public int $minSize = 2,
        public int $maxSize = 10,
        public int $idleTimeout = 600,
        public int $waitTimeout = 30,
        public string $algorithm = 'round_robin',
        public bool $validationOnAcquire = true,
        public bool $warmupOnBoot = true,
        public int $cleanupInterval = 60
    ) {
        $this->validate();
    }

    /**
     * Create from config array.
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            minSize: (int) ($config['min_size'] ?? 2),
            maxSize: (int) ($config['max_size'] ?? 10),
            idleTimeout: (int) ($config['idle_timeout'] ?? 600),
            waitTimeout: (int) ($config['wait_timeout'] ?? 30),
            algorithm: (string) ($config['algorithm'] ?? 'round_robin'),
            validationOnAcquire: (bool) ($config['validation_on_acquire'] ?? true),
            warmupOnBoot: (bool) ($config['warmup_on_boot'] ?? true),
            cleanupInterval: (int) ($config['cleanup_interval'] ?? 60)
        );
    }

    /**
     * Create from Laravel config for a specific connection.
     */
    public static function forConnection(string $connection = 'default'): self
    {
        /** @var array<string, mixed> $connectionConfig */
        $connectionConfig = config("sap-b1.pool.connections.{$connection}", []);

        /** @var array<string, mixed> $poolConfig */
        $poolConfig = config('sap-b1.pool', []);

        return new self(
            minSize: (int) ($connectionConfig['min_size'] ?? 2),
            maxSize: (int) ($connectionConfig['max_size'] ?? 10),
            idleTimeout: (int) ($connectionConfig['idle_timeout'] ?? 600),
            waitTimeout: (int) ($connectionConfig['wait_timeout'] ?? 30),
            algorithm: (string) ($poolConfig['algorithm'] ?? 'round_robin'),
            validationOnAcquire: (bool) ($poolConfig['validation_on_acquire'] ?? true),
            warmupOnBoot: (bool) ($poolConfig['warmup_on_boot'] ?? true),
            cleanupInterval: (int) ($poolConfig['cleanup_interval'] ?? 60)
        );
    }

    /**
     * Check if pool is enabled.
     */
    public static function isEnabled(): bool
    {
        return (bool) config('sap-b1.pool.enabled', false);
    }

    /**
     * Get supported algorithms.
     *
     * @return array<string>
     */
    public static function supportedAlgorithms(): array
    {
        return ['round_robin', 'least_connections', 'lifo'];
    }

    /**
     * Validate configuration values.
     *
     * @throws PoolConfigurationException
     */
    private function validate(): void
    {
        if ($this->minSize < 0) {
            throw new PoolConfigurationException('min_size must be >= 0');
        }

        if ($this->maxSize < 1) {
            throw new PoolConfigurationException('max_size must be >= 1');
        }

        if ($this->minSize > $this->maxSize) {
            throw new PoolConfigurationException('min_size cannot be greater than max_size');
        }

        if ($this->idleTimeout < 0) {
            throw new PoolConfigurationException('idle_timeout must be >= 0');
        }

        if ($this->waitTimeout < 0) {
            throw new PoolConfigurationException('wait_timeout must be >= 0');
        }

        if (! in_array($this->algorithm, self::supportedAlgorithms(), true)) {
            throw new PoolConfigurationException(
                "Invalid algorithm '{$this->algorithm}'. Supported: ".implode(', ', self::supportedAlgorithms())
            );
        }
    }
}
