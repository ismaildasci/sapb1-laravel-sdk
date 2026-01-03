<?php

declare(strict_types=1);

use SapB1\Client\ODataBuilder;

describe('OData Sanitization', function (): void {
    it('allows valid field names', function (): void {
        $builder = ODataBuilder::make()
            ->where('CardCode', 'C001')
            ->where('CardName', 'eq', 'Test Company')
            ->where('U_CustomField', 'value')
            ->where('Address/City', 'Istanbul');

        $query = urldecode($builder->build());

        expect($query)->toContain('CardCode');
        expect($query)->toContain('U_CustomField');
        expect($query)->toContain('Address/City');
    });

    it('rejects invalid field names in strict mode', function (): void {
        $builder = ODataBuilder::make();

        expect(fn () => $builder->where('Card Code', 'value'))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $builder->where('123Invalid', 'value'))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $builder->where('field;DROP TABLE', 'value'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('allows valid operators', function (): void {
        $builder = ODataBuilder::make()
            ->where('CardCode', 'eq', 'C001')
            ->where('DocTotal', 'gt', 100)
            ->where('DocTotal', 'ge', 50)
            ->where('DocTotal', 'lt', 1000)
            ->where('DocTotal', 'le', 500)
            ->where('Status', 'ne', 'Closed');

        expect($builder->build())->toContain('eq');
        expect($builder->build())->toContain('gt');
    });

    it('rejects invalid operators in strict mode', function (): void {
        $builder = ODataBuilder::make();

        expect(fn () => $builder->where('CardCode', 'like', 'value'))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $builder->where('CardCode', 'contains', 'value'))
            ->toThrow(InvalidArgumentException::class);

        expect(fn () => $builder->where('CardCode', '; DROP TABLE', 'value'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('allows any input when strict mode is disabled', function (): void {
        $builder = ODataBuilder::make()
            ->withoutStrictMode()
            ->where('Invalid_Field', 'eq', 'value');

        $query = urldecode($builder->build());

        expect($query)->toContain('Invalid_Field');
    });

    it('validates orWhere the same as where', function (): void {
        $builder = ODataBuilder::make()
            ->where('CardCode', 'C001');

        expect(fn () => $builder->orWhere('Invalid;Field', 'value'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('allows nested field paths with dots', function (): void {
        $builder = ODataBuilder::make()
            ->where('AddressExtension.City', 'Istanbul');

        expect($builder->build())->toContain('AddressExtension.City');
    });

    it('escapes single quotes in string values', function (): void {
        $builder = ODataBuilder::make()
            ->where('CardName', "O'Reilly");

        $query = urldecode($builder->build());

        expect($query)->toContain("O''Reilly");
    });
});
