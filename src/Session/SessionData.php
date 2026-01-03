<?php

declare(strict_types=1);

namespace SapB1\Session;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use SapB1\Exceptions\JsonDecodeException;

/**
 * @implements Arrayable<string, mixed>
 */
readonly class SessionData implements Arrayable, JsonSerializable
{
    public function __construct(
        public string $sessionId,
        public string $routeId,
        public string $companyDb,
        public CarbonImmutable $expiresAt,
        public CarbonImmutable $createdAt
    ) {}

    /**
     * Create SessionData from SAP B1 login response.
     *
     * @param  array<string, mixed>  $response
     */
    public static function fromLoginResponse(array $response, string $companyDb, int $ttl = 1680): self
    {
        return new self(
            sessionId: $response['SessionId'] ?? '',
            routeId: $response['RouteId'] ?? '',
            companyDb: $companyDb,
            expiresAt: CarbonImmutable::now()->addSeconds($ttl),
            createdAt: CarbonImmutable::now()
        );
    }

    /**
     * Create SessionData from array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sessionId: $data['session_id'] ?? $data['sessionId'] ?? '',
            routeId: $data['route_id'] ?? $data['routeId'] ?? '',
            companyDb: $data['company_db'] ?? $data['companyDb'] ?? '',
            expiresAt: CarbonImmutable::parse($data['expires_at'] ?? $data['expiresAt']),
            createdAt: CarbonImmutable::parse($data['created_at'] ?? $data['createdAt'])
        );
    }

    /**
     * Create SessionData from JSON string.
     *
     * @throws JsonDecodeException
     */
    public static function fromJson(string $json): self
    {
        /** @var array<string, mixed>|null $data */
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw JsonDecodeException::fromLastError($json);
        }

        if ($data === null) {
            throw new JsonDecodeException('Session data is null');
        }

        return self::fromArray($data);
    }

    /**
     * Check if session has expired.
     */
    public function isExpired(): bool
    {
        return $this->expiresAt->isPast();
    }

    /**
     * Check if session is near expiry.
     */
    public function isNearExpiry(int $threshold = 300): bool
    {
        return $this->expiresAt->subSeconds($threshold)->isPast();
    }

    /**
     * Create a new instance with refreshed expiry time.
     */
    public function refresh(int $ttl = 1680): self
    {
        return new self(
            sessionId: $this->sessionId,
            routeId: $this->routeId,
            companyDb: $this->companyDb,
            expiresAt: CarbonImmutable::now()->addSeconds($ttl),
            createdAt: $this->createdAt
        );
    }

    /**
     * Get session headers for API requests.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [
            'Cookie' => "B1SESSION={$this->sessionId}; ROUTEID={$this->routeId}",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'route_id' => $this->routeId,
            'company_db' => $this->companyDb,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'created_at' => $this->createdAt->toIso8601String(),
        ];
    }

    public function toJson(): string
    {
        return (string) json_encode($this->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
