<?php

declare(strict_types=1);

namespace SapB1\Services;

use Illuminate\Support\Collection;
use SapB1\Client\SapB1Client;

class AuditService
{
    protected ?string $entityType = null;

    protected ?string $entityKey = null;

    protected ?string $sinceDate = null;

    protected ?string $untilDate = null;

    protected ?string $user = null;

    public function __construct(
        protected SapB1Client $client,
        protected string $connection = 'default'
    ) {}

    /**
     * Set entity type to query.
     */
    public function entity(string $type): self
    {
        $this->entityType = $type;

        return $this;
    }

    /**
     * Set entity key to query.
     */
    public function key(string $key): self
    {
        $this->entityKey = $key;

        return $this;
    }

    /**
     * Filter changes since date.
     */
    public function since(string $date): self
    {
        $this->sinceDate = $date;

        return $this;
    }

    /**
     * Filter changes until date.
     */
    public function until(string $date): self
    {
        $this->untilDate = $date;

        return $this;
    }

    /**
     * Filter changes between dates.
     */
    public function between(string $start, string $end): self
    {
        $this->sinceDate = $start;
        $this->untilDate = $end;

        return $this;
    }

    /**
     * Filter by user.
     */
    public function user(string $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get change log records.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function get(): Collection
    {
        // SAP B1 stores change history in tables with 'A' prefix
        // e.g., OCRD -> ACRD, OITM -> AITM
        if ($this->entityType === null) {
            return collect();
        }

        $historyTable = $this->getHistoryTableName($this->entityType);

        // Use SQL query to access history tables directly
        $sqlQuery = $this->client
            ->connection($this->connection)
            ->sql($historyTable);

        if ($this->entityKey !== null) {
            $keyField = $this->getKeyField($this->entityType);
            $sqlQuery->param($keyField, $this->entityKey);
        }

        try {
            // SqlQueryBuilder::execute() returns array directly
            $records = $sqlQuery->execute();

            return collect($records);
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * Get access log (user login/logout history).
     * Call getAccessLog() after this to retrieve the data.
     */
    public function accessLog(): self
    {
        $this->entityType = '__ACCESS_LOG__';

        return $this;
    }

    /**
     * Query access log.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function getAccessLog(): Collection
    {
        $service = $this->client
            ->connection($this->connection)
            ->service('UserAccessLog');

        if ($this->user !== null) {
            $service->where('UserCode', $this->user);
        }

        if ($this->sinceDate !== null) {
            $service->where('LoginDate', 'ge', $this->sinceDate);
        }

        if ($this->untilDate !== null) {
            $service->where('LoginDate', 'le', $this->untilDate);
        }

        $service->orderByDesc('LoginDate');

        try {
            $result = $service->get();

            /** @var array<int, array<string, mixed>> $records */
            $records = $result['value'] ?? $result;

            return collect($records);
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * Get history table name for an entity.
     */
    protected function getHistoryTableName(string $entity): string
    {
        // Common entity to table mappings
        $tableMapping = [
            'BusinessPartners' => 'ACRD',
            'Items' => 'AITM',
            'Orders' => 'ARDR',
            'Invoices' => 'AINV',
            'PurchaseOrders' => 'APOR',
            'Quotations' => 'AQUT',
            'DeliveryNotes' => 'ADLN',
        ];

        return $tableMapping[$entity] ?? 'A'.substr($entity, 1);
    }

    /**
     * Get key field for an entity.
     */
    protected function getKeyField(string $entity): string
    {
        $keyFields = [
            'BusinessPartners' => 'CardCode',
            'Items' => 'ItemCode',
            'Orders' => 'DocEntry',
            'Invoices' => 'DocEntry',
        ];

        return $keyFields[$entity] ?? 'DocEntry';
    }

    /**
     * Use a different connection.
     */
    public function connection(string $connection): self
    {
        $clone = clone $this;
        $clone->connection = $connection;

        return $clone;
    }

    /**
     * Reset query state.
     */
    public function reset(): self
    {
        $this->entityType = null;
        $this->entityKey = null;
        $this->sinceDate = null;
        $this->untilDate = null;
        $this->user = null;

        return $this;
    }
}
