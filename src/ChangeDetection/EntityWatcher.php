<?php

declare(strict_types=1);

namespace SapB1\ChangeDetection;

use Closure;

class EntityWatcher
{
    protected string $keyField = 'DocEntry';

    /** @var array<int, string> */
    protected array $trackFields = [];

    /** @var array<int, array{field: string, operator: string, value: mixed}> */
    protected array $filters = [];

    /** @var array<int, Closure> */
    protected array $createdCallbacks = [];

    /** @var array<int, Closure> */
    protected array $updatedCallbacks = [];

    /** @var array<int, Closure> */
    protected array $deletedCallbacks = [];

    protected int $limit = 1000;

    public function __construct(
        protected string $entity
    ) {
        // Set default key field based on entity type
        $this->keyField = $this->guessKeyField($entity);
    }

    /**
     * Set the key field for identifying records.
     */
    public function keyField(string $field): self
    {
        $this->keyField = $field;

        return $this;
    }

    /**
     * Get the key field.
     */
    public function getKeyField(): string
    {
        return $this->keyField;
    }

    /**
     * Set fields to track for changes.
     */
    public function track(string ...$fields): self
    {
        $this->trackFields = array_values(array_merge($this->trackFields, $fields));

        return $this;
    }

    /**
     * Get tracked fields.
     *
     * @return array<int, string>
     */
    public function getTrackFields(): array
    {
        return $this->trackFields;
    }

    /**
     * Add a filter to limit watched records.
     */
    public function where(string $field, mixed $operatorOrValue, mixed $value = null): self
    {
        if ($value === null) {
            $value = $operatorOrValue;
            $operator = 'eq';
        } else {
            $operator = $operatorOrValue;
        }

        $this->filters[] = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * Check if watcher has filters.
     */
    public function hasFilters(): bool
    {
        return ! empty($this->filters);
    }

    /**
     * Get filters.
     *
     * @return array<int, array{field: string, operator: string, value: mixed}>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Set maximum records to track.
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Get limit.
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Register callback for created records.
     *
     * @param  Closure(array<string, mixed>, string): void  $callback
     */
    public function onCreated(Closure $callback): self
    {
        $this->createdCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register callback for updated records.
     *
     * @param  Closure(array<string, mixed>, array<string, array{from: mixed, to: mixed}>, string): void  $callback
     */
    public function onUpdated(Closure $callback): self
    {
        $this->updatedCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register callback for deleted records.
     *
     * @param  Closure(string, array<string, mixed>): void  $callback
     */
    public function onDeleted(Closure $callback): self
    {
        $this->deletedCallbacks[] = $callback;

        return $this;
    }

    /**
     * Get created callbacks.
     *
     * @return array<int, Closure>
     */
    public function getCreatedCallbacks(): array
    {
        return $this->createdCallbacks;
    }

    /**
     * Get updated callbacks.
     *
     * @return array<int, Closure>
     */
    public function getUpdatedCallbacks(): array
    {
        return $this->updatedCallbacks;
    }

    /**
     * Get deleted callbacks.
     *
     * @return array<int, Closure>
     */
    public function getDeletedCallbacks(): array
    {
        return $this->deletedCallbacks;
    }

    /**
     * Guess the key field based on entity name.
     */
    protected function guessKeyField(string $entity): string
    {
        // Document entities use DocEntry
        $documentEntities = [
            'Orders', 'Invoices', 'DeliveryNotes', 'Returns', 'CreditNotes',
            'PurchaseOrders', 'PurchaseDeliveryNotes', 'PurchaseInvoices',
            'Quotations', 'DownPayments', 'Drafts',
            'InventoryTransferRequests', 'StockTransfers', 'InventoryGenEntries',
            'JournalEntries', 'Payments', 'IncomingPayments',
        ];

        if (in_array($entity, $documentEntities, true)) {
            return 'DocEntry';
        }

        // Master data entities
        $masterDataKeyFields = [
            'BusinessPartners' => 'CardCode',
            'Items' => 'ItemCode',
            'Warehouses' => 'WarehouseCode',
            'PriceLists' => 'PriceListNo',
            'Users' => 'UserCode',
            'Employees' => 'EmployeeID',
            'SalesPersons' => 'SalesEmployeeCode',
            'Projects' => 'Code',
            'ChartOfAccounts' => 'Code',
        ];

        return $masterDataKeyFields[$entity] ?? 'Code';
    }
}
