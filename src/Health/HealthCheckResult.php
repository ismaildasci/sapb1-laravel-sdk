<?php

declare(strict_types=1);

namespace SapB1\Health;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class HealthCheckResult implements Arrayable, JsonSerializable
{
    public function __construct(
        public bool $healthy,
        public string $message,
        public ?float $responseTime = null,
        public ?string $connection = null,
        public ?string $companyDb = null,
        public ?string $sessionId = null,
        public ?\DateTimeInterface $checkedAt = null
    ) {}

    /**
     * Create a healthy result.
     */
    public static function healthy(
        string $message = 'SAP B1 connection is healthy',
        ?float $responseTime = null,
        ?string $connection = null,
        ?string $companyDb = null,
        ?string $sessionId = null
    ): self {
        return new self(
            healthy: true,
            message: $message,
            responseTime: $responseTime,
            connection: $connection,
            companyDb: $companyDb,
            sessionId: $sessionId,
            checkedAt: new \DateTimeImmutable
        );
    }

    /**
     * Create an unhealthy result.
     */
    public static function unhealthy(
        string $message,
        ?string $connection = null
    ): self {
        return new self(
            healthy: false,
            message: $message,
            connection: $connection,
            checkedAt: new \DateTimeImmutable
        );
    }

    /**
     * Check if the result is healthy.
     */
    public function isHealthy(): bool
    {
        return $this->healthy;
    }

    /**
     * Check if the result is unhealthy.
     */
    public function isUnhealthy(): bool
    {
        return ! $this->healthy;
    }

    /**
     * Get the status string.
     */
    public function status(): string
    {
        return $this->healthy ? 'healthy' : 'unhealthy';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status(),
            'healthy' => $this->healthy,
            'message' => $this->message,
            'response_time_ms' => $this->responseTime !== null ? round($this->responseTime, 2) : null,
            'connection' => $this->connection,
            'company_db' => $this->companyDb,
            'session_id' => $this->sessionId,
            'checked_at' => $this->checkedAt?->format('c'),
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
