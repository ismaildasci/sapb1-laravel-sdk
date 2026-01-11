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
        // Comprehensive entity to history table mappings
        // SAP B1 uses 'A' prefix for audit/history tables
        $tableMapping = [
            // Master Data
            'BusinessPartners' => 'ACRD',
            'Items' => 'AITM',
            'Warehouses' => 'AWHS',
            'PriceLists' => 'APLN',
            'SalesPersons' => 'ASLN',
            'Employees' => 'AHEP',
            'ChartOfAccounts' => 'AACT',
            'Projects' => 'APRJ',
            'Users' => 'AUSR',
            'ItemGroups' => 'AITB',
            'BusinessPartnerGroups' => 'ACRG',

            // Sales Documents
            'Orders' => 'ARDR',
            'Invoices' => 'AINV',
            'DeliveryNotes' => 'ADLN',
            'Returns' => 'ARDN',
            'CreditNotes' => 'ARIN',
            'Quotations' => 'AQUT',
            'DownPayments' => 'ADPI',

            // Purchasing Documents
            'PurchaseOrders' => 'APOR',
            'PurchaseDeliveryNotes' => 'APDN',
            'PurchaseInvoices' => 'APCH',
            'PurchaseReturns' => 'ARPC',
            'PurchaseCreditNotes' => 'ARPC',
            'PurchaseQuotations' => 'APQT',

            // Inventory Documents
            'InventoryGenEntries' => 'AIGE',
            'InventoryGenExits' => 'AIGE',
            'StockTransfers' => 'AWTR',
            'InventoryTransferRequests' => 'AWTQ',
            'InventoryCountings' => 'AINC',

            // Financial Documents
            'JournalEntries' => 'AJDT',
            'Payments' => 'AVPM',
            'IncomingPayments' => 'ARCT',
            'CorrectionInvoice' => 'ARPC',
            'CorrectionInvoiceReversal' => 'ARPC',

            // Production
            'ProductionOrders' => 'AWOR',
            'BillOfMaterials' => 'AITT',

            // Service
            'ServiceCalls' => 'ASCL',
            'ServiceContracts' => 'ACTR',

            // Banking
            'BankStatements' => 'AOBS',
            'Checks' => 'ACHK',

            // Draft Documents
            'Drafts' => 'ADRF',
        ];

        return $tableMapping[$entity] ?? 'A'.substr($entity, 1);
    }

    /**
     * Get key field for an entity.
     */
    protected function getKeyField(string $entity): string
    {
        $keyFields = [
            // Master Data with code-based keys
            'BusinessPartners' => 'CardCode',
            'Items' => 'ItemCode',
            'Warehouses' => 'WhsCode',
            'ChartOfAccounts' => 'AcctCode',
            'Projects' => 'PrjCode',
            'Users' => 'USER_CODE',
            'SalesPersons' => 'SlpCode',
            'PriceLists' => 'ListNum',
            'ItemGroups' => 'ItmsGrpCod',
            'BusinessPartnerGroups' => 'GroupCode',

            // Documents with DocEntry keys
            'Orders' => 'DocEntry',
            'Invoices' => 'DocEntry',
            'DeliveryNotes' => 'DocEntry',
            'Returns' => 'DocEntry',
            'CreditNotes' => 'DocEntry',
            'Quotations' => 'DocEntry',
            'DownPayments' => 'DocEntry',
            'PurchaseOrders' => 'DocEntry',
            'PurchaseDeliveryNotes' => 'DocEntry',
            'PurchaseInvoices' => 'DocEntry',
            'PurchaseReturns' => 'DocEntry',
            'PurchaseCreditNotes' => 'DocEntry',
            'PurchaseQuotations' => 'DocEntry',
            'JournalEntries' => 'TransId',
            'Payments' => 'DocEntry',
            'IncomingPayments' => 'DocEntry',
            'StockTransfers' => 'DocEntry',
            'InventoryTransferRequests' => 'DocEntry',
            'InventoryGenEntries' => 'DocEntry',
            'InventoryGenExits' => 'DocEntry',
            'ProductionOrders' => 'DocEntry',
            'Drafts' => 'DocEntry',

            // Other keys
            'Employees' => 'empID',
            'ServiceCalls' => 'callID',
            'ServiceContracts' => 'ContractID',
            'BillOfMaterials' => 'Code',
        ];

        return $keyFields[$entity] ?? 'DocEntry';
    }

    /**
     * Get supported entities for audit.
     *
     * @return array<string, array{table: string, key: string}>
     */
    public static function getSupportedEntities(): array
    {
        return [
            'BusinessPartners' => ['table' => 'ACRD', 'key' => 'CardCode'],
            'Items' => ['table' => 'AITM', 'key' => 'ItemCode'],
            'Warehouses' => ['table' => 'AWHS', 'key' => 'WhsCode'],
            'Orders' => ['table' => 'ARDR', 'key' => 'DocEntry'],
            'Invoices' => ['table' => 'AINV', 'key' => 'DocEntry'],
            'DeliveryNotes' => ['table' => 'ADLN', 'key' => 'DocEntry'],
            'Returns' => ['table' => 'ARDN', 'key' => 'DocEntry'],
            'CreditNotes' => ['table' => 'ARIN', 'key' => 'DocEntry'],
            'Quotations' => ['table' => 'AQUT', 'key' => 'DocEntry'],
            'PurchaseOrders' => ['table' => 'APOR', 'key' => 'DocEntry'],
            'PurchaseDeliveryNotes' => ['table' => 'APDN', 'key' => 'DocEntry'],
            'PurchaseInvoices' => ['table' => 'APCH', 'key' => 'DocEntry'],
            'JournalEntries' => ['table' => 'AJDT', 'key' => 'TransId'],
            'Payments' => ['table' => 'AVPM', 'key' => 'DocEntry'],
            'IncomingPayments' => ['table' => 'ARCT', 'key' => 'DocEntry'],
            'StockTransfers' => ['table' => 'AWTR', 'key' => 'DocEntry'],
            'ProductionOrders' => ['table' => 'AWOR', 'key' => 'DocEntry'],
        ];
    }

    /**
     * Check if entity is supported for audit.
     */
    public static function isEntitySupported(string $entity): bool
    {
        return array_key_exists($entity, self::getSupportedEntities());
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
