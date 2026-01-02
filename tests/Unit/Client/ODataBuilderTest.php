<?php

declare(strict_types=1);

use SapB1\Client\ODataBuilder;

describe('ODataBuilder', function (): void {
    it('can be created with static make method', function (): void {
        $builder = ODataBuilder::make();

        expect($builder)->toBeInstanceOf(ODataBuilder::class);
    });

    it('builds select query', function (): void {
        $builder = ODataBuilder::make()
            ->select('CardCode', 'CardName');

        expect($builder->toArray()['$select'])->toBe('CardCode,CardName');
    });

    it('builds select query with array', function (): void {
        $builder = ODataBuilder::make()
            ->select(['CardCode', 'CardName', 'Phone1']);

        expect($builder->toArray()['$select'])->toBe('CardCode,CardName,Phone1');
    });

    it('builds filter query', function (): void {
        $builder = ODataBuilder::make()
            ->filter("CardType eq 'cCustomer'");

        expect($builder->toArray()['$filter'])->toBe("CardType eq 'cCustomer'");
    });

    it('builds where query with eq operator', function (): void {
        $builder = ODataBuilder::make()
            ->where('CardCode', 'C001');

        expect($builder->toArray()['$filter'])->toBe("CardCode eq 'C001'");
    });

    it('builds where query with explicit operator', function (): void {
        $builder = ODataBuilder::make()
            ->where('DocTotal', 'gt', 1000);

        expect($builder->toArray()['$filter'])->toBe('DocTotal gt 1000');
    });

    it('builds multiple filters with and', function (): void {
        $builder = ODataBuilder::make()
            ->where('CardType', 'cCustomer')
            ->where('Frozen', 'tNO');

        $params = $builder->toArray();

        expect($params['$filter'])->toContain('and');
    });

    it('builds whereIn query', function (): void {
        $builder = ODataBuilder::make()
            ->whereIn('CardCode', ['C001', 'C002', 'C003']);

        $params = $builder->toArray();

        expect($params['$filter'])->toContain('CardCode eq')
            ->and($params['$filter'])->toContain('or');
    });

    it('builds whereContains query', function (): void {
        $builder = ODataBuilder::make()
            ->whereContains('CardName', 'Test');

        $params = $builder->toArray();

        expect($params['$filter'])->toContain("contains(CardName, 'Test')");
    });

    it('builds whereStartsWith query', function (): void {
        $builder = ODataBuilder::make()
            ->whereStartsWith('CardCode', 'C');

        $params = $builder->toArray();

        expect($params['$filter'])->toContain("startswith(CardCode, 'C')");
    });

    it('builds whereNull query', function (): void {
        $builder = ODataBuilder::make()
            ->whereNull('Phone2');

        $params = $builder->toArray();

        expect($params['$filter'])->toBe('Phone2 eq null');
    });

    it('builds whereBetween query', function (): void {
        $builder = ODataBuilder::make()
            ->whereBetween('DocTotal', 100, 1000);

        $params = $builder->toArray();

        expect($params['$filter'])->toContain('DocTotal ge 100')
            ->and($params['$filter'])->toContain('DocTotal le 1000');
    });

    it('builds orderBy query', function (): void {
        $builder = ODataBuilder::make()
            ->orderBy('CardName');

        expect($builder->toArray()['$orderby'])->toBe('CardName asc');
    });

    it('builds orderByDesc query', function (): void {
        $builder = ODataBuilder::make()
            ->orderByDesc('DocDate');

        expect($builder->toArray()['$orderby'])->toBe('DocDate desc');
    });

    it('builds top query', function (): void {
        $builder = ODataBuilder::make()
            ->top(10);

        expect($builder->toArray()['$top'])->toBe('10');
    });

    it('builds skip query', function (): void {
        $builder = ODataBuilder::make()
            ->skip(20);

        expect($builder->toArray()['$skip'])->toBe('20');
    });

    it('builds page query', function (): void {
        $builder = ODataBuilder::make()
            ->page(3, 20);

        $params = $builder->toArray();

        expect($params['$top'])->toBe('20')
            ->and($params['$skip'])->toBe('40');
    });

    it('builds expand query', function (): void {
        $builder = ODataBuilder::make()
            ->expand('ContactEmployees', 'BPAddresses');

        expect($builder->toArray()['$expand'])->toBe('ContactEmployees,BPAddresses');
    });

    it('builds inlineCount query', function (): void {
        $builder = ODataBuilder::make()
            ->inlineCount();

        expect($builder->toArray()['$inlinecount'])->toBe('allpages');
    });

    it('uses limit as alias for top', function (): void {
        $builder = ODataBuilder::make()->limit(5);

        expect($builder->toArray()['$top'])->toBe('5');
    });

    it('uses offset as alias for skip', function (): void {
        $builder = ODataBuilder::make()->offset(10);

        expect($builder->toArray()['$skip'])->toBe('10');
    });

    it('formats boolean values', function (): void {
        $builder = ODataBuilder::make()
            ->where('Active', true);

        expect($builder->toArray()['$filter'])->toContain('true');
    });

    it('formats null values', function (): void {
        $builder = ODataBuilder::make()
            ->where('DeletedAt', null);

        expect($builder->toArray()['$filter'])->toContain('null');
    });

    it('escapes single quotes in strings', function (): void {
        $builder = ODataBuilder::make()
            ->where('CardName', "O'Brien");

        expect($builder->toArray()['$filter'])->toContain("O''Brien");
    });

    it('returns empty string when no params', function (): void {
        $query = ODataBuilder::make()->build();

        expect($query)->toBe('');
    });

    it('can be reset', function (): void {
        $builder = ODataBuilder::make()
            ->select('CardCode')
            ->where('CardType', 'cCustomer')
            ->top(10)
            ->reset();

        expect($builder->toArray())->toBeEmpty();
    });

    it('can be cloned', function (): void {
        $original = ODataBuilder::make()->select('CardCode');
        $cloned = $original->clone()->select('CardName');

        expect($original->toArray()['$select'])->toBe('CardCode');
        expect($cloned->toArray()['$select'])->toBe('CardCode,CardName');
    });

    it('can be converted to string', function (): void {
        $builder = ODataBuilder::make()->top(5);
        $query = (string) $builder;

        // URL encoded query string
        expect($query)->toStartWith('?')
            ->and($query)->toContain('top');
    });
});
