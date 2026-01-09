<?php

declare(strict_types=1);

namespace SapB1\Contracts;

interface TenantResolverInterface
{
    /**
     * Resolve the current tenant ID.
     */
    public function resolve(): ?string;

    /**
     * Get configuration for a specific tenant.
     *
     * @return array<string, mixed>|null
     */
    public function getConfig(string $tenantId): ?array;
}
