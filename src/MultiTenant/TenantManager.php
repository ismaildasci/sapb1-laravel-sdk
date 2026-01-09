<?php

declare(strict_types=1);

namespace SapB1\MultiTenant;

use Closure;
use SapB1\Contracts\TenantResolverInterface;

class TenantManager
{
    protected ?string $currentTenantId = null;

    /** @var array<string, array<string, mixed>> */
    protected array $tenantConfigs = [];

    protected ?TenantResolverInterface $resolver = null;

    protected ?Closure $resolverCallback = null;

    /**
     * Set the current tenant.
     */
    public function setTenant(string $tenantId): self
    {
        $this->currentTenantId = $tenantId;

        return $this;
    }

    /**
     * Get the current tenant ID.
     */
    public function getTenantId(): ?string
    {
        if ($this->currentTenantId !== null) {
            return $this->currentTenantId;
        }

        return $this->resolveTenant();
    }

    /**
     * Check if a tenant is set.
     */
    public function hasTenant(): bool
    {
        return $this->getTenantId() !== null;
    }

    /**
     * Clear the current tenant.
     */
    public function clearTenant(): self
    {
        $this->currentTenantId = null;

        return $this;
    }

    /**
     * Set tenant resolver.
     */
    public function setResolver(TenantResolverInterface|Closure $resolver): self
    {
        if ($resolver instanceof TenantResolverInterface) {
            $this->resolver = $resolver;
            $this->resolverCallback = null;
        } else {
            $this->resolver = null;
            $this->resolverCallback = $resolver;
        }

        return $this;
    }

    /**
     * Register tenant configuration.
     *
     * @param  array<string, mixed>  $config
     */
    public function registerTenant(string $tenantId, array $config): self
    {
        $this->tenantConfigs[$tenantId] = $config;

        return $this;
    }

    /**
     * Get configuration for a tenant.
     *
     * @return array<string, mixed>|null
     */
    public function getTenantConfig(?string $tenantId = null): ?array
    {
        $tenantId = $tenantId ?? $this->getTenantId();

        if ($tenantId === null) {
            return null;
        }

        // Check registered configs first
        if (isset($this->tenantConfigs[$tenantId])) {
            return $this->tenantConfigs[$tenantId];
        }

        // Try to resolve via resolver
        return $this->resolveConfig($tenantId);
    }

    /**
     * Get SAP B1 connection config for current tenant.
     *
     * @return array<string, mixed>|null
     */
    public function getConnectionConfig(): ?array
    {
        $config = $this->getTenantConfig();

        if ($config === null) {
            return null;
        }

        return [
            'base_url' => $config['sap_url'] ?? $config['base_url'] ?? null,
            'company_db' => $config['sap_database'] ?? $config['company_db'] ?? null,
            'username' => $config['sap_username'] ?? $config['username'] ?? null,
            'password' => $config['sap_password'] ?? $config['password'] ?? null,
            'language' => $config['sap_language'] ?? $config['language'] ?? 23,
            'odata_version' => $config['odata_version'] ?? 'v1',
        ];
    }

    /**
     * Execute callback within tenant context.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runAs(string $tenantId, Closure $callback): mixed
    {
        $previousTenant = $this->currentTenantId;

        try {
            $this->setTenant($tenantId);

            return $callback();
        } finally {
            $this->currentTenantId = $previousTenant;
        }
    }

    /**
     * Get all registered tenant IDs.
     *
     * @return array<int, string>
     */
    public function getRegisteredTenants(): array
    {
        return array_keys($this->tenantConfigs);
    }

    /**
     * Check if tenant is registered.
     */
    public function isRegistered(string $tenantId): bool
    {
        return isset($this->tenantConfigs[$tenantId]);
    }

    /**
     * Resolve current tenant ID.
     */
    protected function resolveTenant(): ?string
    {
        if ($this->resolver !== null) {
            return $this->resolver->resolve();
        }

        if ($this->resolverCallback !== null) {
            return ($this->resolverCallback)();
        }

        return null;
    }

    /**
     * Resolve config for a tenant.
     *
     * @return array<string, mixed>|null
     */
    protected function resolveConfig(string $tenantId): ?array
    {
        if ($this->resolver !== null) {
            return $this->resolver->getConfig($tenantId);
        }

        return null;
    }
}
