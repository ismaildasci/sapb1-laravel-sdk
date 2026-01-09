<?php

declare(strict_types=1);

namespace SapB1\Services;

use SapB1\Client\SapB1Client;

class CompanyService
{
    /** @var array<string, mixed>|null */
    protected ?array $cachedInfo = null;

    /** @var array<string, mixed>|null */
    protected ?array $cachedAdminInfo = null;

    public function __construct(
        protected SapB1Client $client,
        protected string $connection = 'default'
    ) {}

    /**
     * Get company information.
     *
     * @return array<string, mixed>|null
     */
    public function info(): ?array
    {
        if ($this->cachedInfo !== null) {
            return $this->cachedInfo;
        }

        $response = $this->client
            ->connection($this->connection)
            ->create('CompanyService_GetCompanyInfo', []);

        if ($response->failed()) {
            return null;
        }

        $this->cachedInfo = $response->entity();

        return $this->cachedInfo;
    }

    /**
     * Get admin information.
     *
     * @return array<string, mixed>|null
     */
    public function adminInfo(): ?array
    {
        if ($this->cachedAdminInfo !== null) {
            return $this->cachedAdminInfo;
        }

        $response = $this->client
            ->connection($this->connection)
            ->create('CompanyService_GetAdminInfo', []);

        if ($response->failed()) {
            return null;
        }

        $this->cachedAdminInfo = $response->entity();

        return $this->cachedAdminInfo;
    }

    /**
     * Get company name.
     */
    public function name(): ?string
    {
        return $this->info()['CompanyName'] ?? null;
    }

    /**
     * Get local currency.
     */
    public function localCurrency(): ?string
    {
        return $this->info()['LocalCurrency'] ?? null;
    }

    /**
     * Get system currency.
     */
    public function systemCurrency(): ?string
    {
        return $this->info()['SystemCurrency'] ?? null;
    }

    /**
     * Get default warehouse.
     */
    public function defaultWarehouse(): ?string
    {
        return $this->adminInfo()['DefaultWarehouse'] ?? null;
    }

    /**
     * Get country.
     */
    public function country(): ?string
    {
        return $this->adminInfo()['Country'] ?? null;
    }

    /**
     * Get current period indicator.
     */
    public function currentPeriod(): ?string
    {
        return $this->adminInfo()['CurrPeriod'] ?? null;
    }

    /**
     * Check if multi-branch is enabled.
     */
    public function isMultiBranch(): bool
    {
        return ($this->adminInfo()['EnableMultiBranch'] ?? 'tNO') === 'tYES';
    }

    /**
     * Get SAP Business One version.
     */
    public function version(): ?string
    {
        return $this->info()['Version'] ?? null;
    }

    /**
     * Get Service Layer info.
     *
     * @return array<string, mixed>|null
     */
    public function serviceLayerInfo(): ?array
    {
        $response = $this->client
            ->connection($this->connection)
            ->get('ServiceLayerInfo');

        if ($response->failed()) {
            return null;
        }

        return $response->entity();
    }

    /**
     * Get database type (HANA or SQL Server).
     */
    public function databaseType(): ?string
    {
        $info = $this->serviceLayerInfo();

        return $info['DatabaseType'] ?? null;
    }

    /**
     * Check if running on HANA.
     */
    public function isHana(): bool
    {
        return str_contains(strtolower($this->databaseType() ?? ''), 'hana');
    }

    /**
     * Get all currencies.
     *
     * @return array<int, array<string, mixed>>
     */
    public function currencies(): array
    {
        $response = $this->client
            ->connection($this->connection)
            ->get('Currencies');

        if ($response->failed()) {
            return [];
        }

        return $response->value();
    }

    /**
     * Get all warehouses.
     *
     * @return array<int, array<string, mixed>>
     */
    public function warehouses(): array
    {
        $response = $this->client
            ->connection($this->connection)
            ->get('Warehouses');

        if ($response->failed()) {
            return [];
        }

        return $response->value();
    }

    /**
     * Get all branches.
     *
     * @return array<int, array<string, mixed>>
     */
    public function branches(): array
    {
        $response = $this->client
            ->connection($this->connection)
            ->get('Branches');

        if ($response->failed()) {
            return [];
        }

        return $response->value();
    }

    /**
     * Get posting periods.
     *
     * @return array<int, array<string, mixed>>
     */
    public function periods(): array
    {
        $response = $this->client
            ->connection($this->connection)
            ->get('FinancialPeriods');

        if ($response->failed()) {
            return [];
        }

        return $response->value();
    }

    /**
     * Clear cached data.
     */
    public function refresh(): self
    {
        $this->cachedInfo = null;
        $this->cachedAdminInfo = null;

        return $this;
    }

    /**
     * Use a different connection.
     */
    public function connection(string $connection): self
    {
        $clone = clone $this;
        $clone->connection = $connection;
        $clone->cachedInfo = null;
        $clone->cachedAdminInfo = null;

        return $clone;
    }
}
