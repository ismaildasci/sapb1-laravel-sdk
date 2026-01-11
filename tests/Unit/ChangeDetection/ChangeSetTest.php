<?php

declare(strict_types=1);

use SapB1\ChangeDetection\ChangeSet;

describe('ChangeSet', function (): void {
    it('creates empty change set', function (): void {
        $changeSet = new ChangeSet('Orders');

        expect($changeSet->isEmpty())->toBeTrue();
        expect($changeSet->count())->toBe(0);
        expect($changeSet->getEntity())->toBe('Orders');
    });

    it('adds created records', function (): void {
        $changeSet = new ChangeSet('Orders');
        $record = ['DocEntry' => 123, 'CardCode' => 'C001'];

        $changeSet->addCreated('123', $record);

        expect($changeSet->hasCreated())->toBeTrue();
        expect($changeSet->getCreated())->toHaveKey('123');
        expect($changeSet->getCreated()['123'])->toBe($record);
    });

    it('adds updated records with changes', function (): void {
        $changeSet = new ChangeSet('Orders');
        $record = ['DocEntry' => 123, 'DocTotal' => 2000];
        $changes = [
            'DocTotal' => ['from' => 1000, 'to' => 2000],
        ];

        $changeSet->addUpdated('123', $record, $changes);

        expect($changeSet->hasUpdated())->toBeTrue();
        expect($changeSet->getUpdated())->toHaveKey('123');
        expect($changeSet->getUpdated()['123']['record'])->toBe($record);
        expect($changeSet->getUpdated()['123']['changes'])->toBe($changes);
    });

    it('adds deleted records', function (): void {
        $changeSet = new ChangeSet('Orders');
        $record = ['DocEntry' => 123, 'CardCode' => 'C001'];

        $changeSet->addDeleted('123', $record);

        expect($changeSet->hasDeleted())->toBeTrue();
        expect($changeSet->getDeleted())->toHaveKey('123');
        expect($changeSet->getDeleted()['123'])->toBe($record);
    });

    it('counts total changes', function (): void {
        $changeSet = new ChangeSet('Orders');

        $changeSet->addCreated('1', ['DocEntry' => 1]);
        $changeSet->addCreated('2', ['DocEntry' => 2]);
        $changeSet->addUpdated('3', ['DocEntry' => 3], []);
        $changeSet->addDeleted('4', ['DocEntry' => 4]);

        expect($changeSet->count())->toBe(4);
        expect($changeSet->isEmpty())->toBeFalse();
    });

    it('checks individual change types', function (): void {
        $changeSet = new ChangeSet('Orders');

        expect($changeSet->hasCreated())->toBeFalse();
        expect($changeSet->hasUpdated())->toBeFalse();
        expect($changeSet->hasDeleted())->toBeFalse();

        $changeSet->addCreated('1', []);
        expect($changeSet->hasCreated())->toBeTrue();
        expect($changeSet->hasUpdated())->toBeFalse();
        expect($changeSet->hasDeleted())->toBeFalse();

        $changeSet->addUpdated('2', [], []);
        expect($changeSet->hasUpdated())->toBeTrue();

        $changeSet->addDeleted('3', []);
        expect($changeSet->hasDeleted())->toBeTrue();
    });

    it('converts to array', function (): void {
        $changeSet = new ChangeSet('Orders');

        $changeSet->addCreated('1', ['DocEntry' => 1]);
        $changeSet->addUpdated('2', ['DocEntry' => 2], ['DocTotal' => ['from' => 100, 'to' => 200]]);
        $changeSet->addDeleted('3', ['DocEntry' => 3]);

        $array = $changeSet->toArray();

        expect($array)->toHaveKey('entity');
        expect($array)->toHaveKey('created');
        expect($array)->toHaveKey('updated');
        expect($array)->toHaveKey('deleted');
        expect($array)->toHaveKey('total_changes');

        expect($array['entity'])->toBe('Orders');
        expect($array['total_changes'])->toBe(3);
    });

    it('returns fluent interface from add methods', function (): void {
        $changeSet = new ChangeSet('Orders');

        expect($changeSet->addCreated('1', []))->toBe($changeSet);
        expect($changeSet->addUpdated('2', [], []))->toBe($changeSet);
        expect($changeSet->addDeleted('3', []))->toBe($changeSet);
    });

    it('handles multiple records with same operation', function (): void {
        $changeSet = new ChangeSet('BusinessPartners');

        $changeSet->addCreated('C001', ['CardCode' => 'C001', 'CardName' => 'Customer 1']);
        $changeSet->addCreated('C002', ['CardCode' => 'C002', 'CardName' => 'Customer 2']);
        $changeSet->addCreated('C003', ['CardCode' => 'C003', 'CardName' => 'Customer 3']);

        expect($changeSet->getCreated())->toHaveCount(3);
        expect($changeSet->count())->toBe(3);
    });

    it('implements Arrayable interface', function (): void {
        $changeSet = new ChangeSet('Items');

        expect($changeSet)->toBeInstanceOf(\Illuminate\Contracts\Support\Arrayable::class);
    });
});
