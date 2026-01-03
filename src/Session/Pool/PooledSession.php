<?php

declare(strict_types=1);

namespace SapB1\Session\Pool;

use Carbon\CarbonImmutable;
use SapB1\Session\SessionData;

/**
 * A session with pool-specific metadata.
 *
 * Wraps SessionData with additional information needed for pool management
 * such as status, acquisition time, and usage count.
 */
readonly class PooledSession
{
    public const STATUS_IDLE = 'idle';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public function __construct(
        public SessionData $session,
        public string $status = self::STATUS_IDLE,
        public ?CarbonImmutable $acquiredAt = null,
        public ?CarbonImmutable $releasedAt = null,
        public int $useCount = 0,
        public CarbonImmutable $createdAt = new CarbonImmutable
    ) {}

    /**
     * Create a PooledSession from a SessionData.
     */
    public static function fromSession(SessionData $session): self
    {
        return new self(
            session: $session,
            status: self::STATUS_IDLE,
            acquiredAt: null,
            releasedAt: null,
            useCount: 0,
            createdAt: CarbonImmutable::now()
        );
    }

    /**
     * Create from array (for deserialization).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            session: SessionData::fromArray($data['session'] ?? $data),
            status: $data['status'] ?? self::STATUS_IDLE,
            acquiredAt: isset($data['acquired_at']) ? CarbonImmutable::parse($data['acquired_at']) : null,
            releasedAt: isset($data['released_at']) ? CarbonImmutable::parse($data['released_at']) : null,
            useCount: $data['use_count'] ?? 0,
            createdAt: isset($data['pool_created_at']) ? CarbonImmutable::parse($data['pool_created_at']) : CarbonImmutable::now()
        );
    }

    /**
     * Check if session is idle (available for acquisition).
     */
    public function isIdle(): bool
    {
        return $this->status === self::STATUS_IDLE;
    }

    /**
     * Check if session is active (currently in use).
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if session is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED || $this->session->isExpired();
    }

    /**
     * Check if session is near expiry.
     */
    public function isNearExpiry(int $threshold = 300): bool
    {
        return $this->session->isNearExpiry($threshold);
    }

    /**
     * Get the session ID.
     */
    public function getSessionId(): string
    {
        return $this->session->sessionId;
    }

    /**
     * Create a new instance with acquired status.
     */
    public function withAcquired(): self
    {
        return new self(
            session: $this->session,
            status: self::STATUS_ACTIVE,
            acquiredAt: CarbonImmutable::now(),
            releasedAt: $this->releasedAt,
            useCount: $this->useCount + 1,
            createdAt: $this->createdAt
        );
    }

    /**
     * Create a new instance with released status.
     */
    public function withReleased(): self
    {
        return new self(
            session: $this->session,
            status: self::STATUS_IDLE,
            acquiredAt: $this->acquiredAt,
            releasedAt: CarbonImmutable::now(),
            useCount: $this->useCount,
            createdAt: $this->createdAt
        );
    }

    /**
     * Create a new instance with expired status.
     */
    public function withExpired(): self
    {
        return new self(
            session: $this->session,
            status: self::STATUS_EXPIRED,
            acquiredAt: $this->acquiredAt,
            releasedAt: $this->releasedAt,
            useCount: $this->useCount,
            createdAt: $this->createdAt
        );
    }

    /**
     * Create a new instance with refreshed session.
     */
    public function withRefreshedSession(int $ttl = 1680): self
    {
        return new self(
            session: $this->session->refresh($ttl),
            status: $this->status,
            acquiredAt: $this->acquiredAt,
            releasedAt: $this->releasedAt,
            useCount: $this->useCount,
            createdAt: $this->createdAt
        );
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session' => $this->session->toArray(),
            'status' => $this->status,
            'acquired_at' => $this->acquiredAt?->toIso8601String(),
            'released_at' => $this->releasedAt?->toIso8601String(),
            'use_count' => $this->useCount,
            'pool_created_at' => $this->createdAt->toIso8601String(),
        ];
    }
}
