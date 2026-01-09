<?php

declare(strict_types=1);

namespace SapB1\MultiTenant;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use SapB1\Contracts\TenantResolverInterface;

/**
 * Example tenant resolver that loads tenant config from database.
 */
class DatabaseTenantResolver implements TenantResolverInterface
{
    public function __construct(
        protected string $table = 'tenants',
        protected string $tenantColumn = 'id',
        protected string $configColumn = 'sap_config'
    ) {}

    /**
     * Resolve tenant from authenticated user.
     */
    public function resolve(): ?string
    {
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        // Assumes user has tenant_id attribute
        return $user->tenant_id ?? null;
    }

    /**
     * Get SAP B1 configuration for tenant from database.
     *
     * @return array<string, mixed>|null
     */
    public function getConfig(string $tenantId): ?array
    {
        $tenant = DB::table($this->table)
            ->where($this->tenantColumn, $tenantId)
            ->first();

        if ($tenant === null) {
            return null;
        }

        $config = $tenant->{$this->configColumn} ?? null;

        if ($config === null) {
            return null;
        }

        // Config might be stored as JSON
        if (is_string($config)) {
            $config = json_decode($config, true);
        }

        return is_array($config) ? $config : null;
    }
}
