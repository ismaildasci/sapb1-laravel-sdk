<?php

declare(strict_types=1);

use SapB1\ChangeDetection\EntityWatcher;

describe('EntityWatcher', function (): void {
    it('guesses DocEntry as key field for document entities', function (): void {
        $documentEntities = [
            'Orders', 'Invoices', 'DeliveryNotes', 'Returns', 'CreditNotes',
            'PurchaseOrders', 'PurchaseDeliveryNotes', 'PurchaseInvoices',
            'Quotations', 'Payments',
        ];

        foreach ($documentEntities as $entity) {
            $watcher = new EntityWatcher($entity);
            expect($watcher->getKeyField())->toBe('DocEntry', "Failed for {$entity}");
        }
    });

    it('guesses CardCode as key field for BusinessPartners', function (): void {
        $watcher = new EntityWatcher('BusinessPartners');

        expect($watcher->getKeyField())->toBe('CardCode');
    });

    it('guesses ItemCode as key field for Items', function (): void {
        $watcher = new EntityWatcher('Items');

        expect($watcher->getKeyField())->toBe('ItemCode');
    });

    it('uses Code as fallback key field', function (): void {
        $watcher = new EntityWatcher('UnknownEntity');

        expect($watcher->getKeyField())->toBe('Code');
    });

    it('allows overriding key field', function (): void {
        $watcher = new EntityWatcher('BusinessPartners');
        $watcher->keyField('CustomKey');

        expect($watcher->getKeyField())->toBe('CustomKey');
    });

    it('tracks specified fields', function (): void {
        $watcher = new EntityWatcher('Orders');
        $watcher->track('DocTotal', 'DocStatus', 'CardCode');

        expect($watcher->getTrackFields())->toBe(['DocTotal', 'DocStatus', 'CardCode']);
    });

    it('accumulates tracked fields', function (): void {
        $watcher = new EntityWatcher('Orders');
        $watcher->track('DocTotal');
        $watcher->track('DocStatus', 'CardCode');

        expect($watcher->getTrackFields())->toBe(['DocTotal', 'DocStatus', 'CardCode']);
    });

    it('adds filters with default eq operator', function (): void {
        $watcher = new EntityWatcher('Orders');
        $watcher->where('CardCode', 'C001');

        $filters = $watcher->getFilters();

        expect($filters)->toHaveCount(1);
        expect($filters[0])->toBe([
            'field' => 'CardCode',
            'operator' => 'eq',
            'value' => 'C001',
        ]);
    });

    it('adds filters with custom operator', function (): void {
        $watcher = new EntityWatcher('Orders');
        $watcher->where('DocTotal', 'gt', 1000);

        $filters = $watcher->getFilters();

        expect($filters[0])->toBe([
            'field' => 'DocTotal',
            'operator' => 'gt',
            'value' => 1000,
        ]);
    });

    it('checks if has filters', function (): void {
        $watcher = new EntityWatcher('Orders');

        expect($watcher->hasFilters())->toBeFalse();

        $watcher->where('CardCode', 'C001');

        expect($watcher->hasFilters())->toBeTrue();
    });

    it('sets limit', function (): void {
        $watcher = new EntityWatcher('Orders');

        expect($watcher->getLimit())->toBe(1000); // default

        $watcher->limit(500);

        expect($watcher->getLimit())->toBe(500);
    });

    it('registers created callback', function (): void {
        $watcher = new EntityWatcher('Orders');
        $callback = fn ($record, $key) => null;

        $watcher->onCreated($callback);

        expect($watcher->getCreatedCallbacks())->toHaveCount(1);
    });

    it('registers updated callback', function (): void {
        $watcher = new EntityWatcher('Orders');
        $callback = fn ($record, $changes, $key) => null;

        $watcher->onUpdated($callback);

        expect($watcher->getUpdatedCallbacks())->toHaveCount(1);
    });

    it('registers deleted callback', function (): void {
        $watcher = new EntityWatcher('Orders');
        $callback = fn ($key, $record) => null;

        $watcher->onDeleted($callback);

        expect($watcher->getDeletedCallbacks())->toHaveCount(1);
    });

    it('registers multiple callbacks of same type', function (): void {
        $watcher = new EntityWatcher('Orders');

        $watcher->onCreated(fn () => null);
        $watcher->onCreated(fn () => null);
        $watcher->onCreated(fn () => null);

        expect($watcher->getCreatedCallbacks())->toHaveCount(3);
    });

    it('returns fluent interface for all setters', function (): void {
        $watcher = new EntityWatcher('Orders');

        expect($watcher->keyField('DocEntry'))->toBe($watcher);
        expect($watcher->track('DocTotal'))->toBe($watcher);
        expect($watcher->where('CardCode', 'C001'))->toBe($watcher);
        expect($watcher->limit(100))->toBe($watcher);
        expect($watcher->onCreated(fn () => null))->toBe($watcher);
        expect($watcher->onUpdated(fn () => null))->toBe($watcher);
        expect($watcher->onDeleted(fn () => null))->toBe($watcher);
    });
});
