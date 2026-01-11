<?php

declare(strict_types=1);

use SapB1\Metadata\FieldInfo;

describe('FieldInfo', function (): void {
    it('creates field info with required properties', function (): void {
        $field = new FieldInfo(
            name: 'CardCode',
            type: 'Edm.String',
        );

        expect($field->name)->toBe('CardCode');
        expect($field->type)->toBe('Edm.String');
        expect($field->nullable)->toBeTrue();
        expect($field->isKey)->toBeFalse();
        expect($field->isUdf)->toBeFalse();
        expect($field->maxLength)->toBeNull();
    });

    it('creates field info with all properties', function (): void {
        $field = new FieldInfo(
            name: 'CardCode',
            type: 'Edm.String',
            nullable: false,
            isKey: true,
            isUdf: false,
            maxLength: 50,
        );

        expect($field->name)->toBe('CardCode');
        expect($field->type)->toBe('Edm.String');
        expect($field->nullable)->toBeFalse();
        expect($field->isKey)->toBeTrue();
        expect($field->isUdf)->toBeFalse();
        expect($field->maxLength)->toBe(50);
    });

    it('identifies user defined fields', function (): void {
        $udf = new FieldInfo(
            name: 'U_CustomField',
            type: 'Edm.String',
            isUdf: true,
        );

        expect($udf->isUdf)->toBeTrue();
    });

    it('identifies key fields', function (): void {
        $keyField = new FieldInfo(
            name: 'DocEntry',
            type: 'Edm.Int32',
            nullable: false,
            isKey: true,
        );

        expect($keyField->isKey)->toBeTrue();
        expect($keyField->nullable)->toBeFalse();
    });

    it('handles different edm types', function (): void {
        $stringField = new FieldInfo('Name', 'Edm.String');
        $intField = new FieldInfo('Count', 'Edm.Int32');
        $doubleField = new FieldInfo('Amount', 'Edm.Double');
        $dateField = new FieldInfo('CreateDate', 'Edm.DateTime');
        $boolField = new FieldInfo('IsActive', 'Edm.Boolean');

        expect($stringField->type)->toBe('Edm.String');
        expect($intField->type)->toBe('Edm.Int32');
        expect($doubleField->type)->toBe('Edm.Double');
        expect($dateField->type)->toBe('Edm.DateTime');
        expect($boolField->type)->toBe('Edm.Boolean');
    });

    it('converts to array', function (): void {
        $field = new FieldInfo(
            name: 'CardCode',
            type: 'Edm.String',
            nullable: false,
            isKey: true,
            isUdf: false,
            maxLength: 50,
        );

        $array = $field->toArray();

        expect($array['name'])->toBe('CardCode');
        expect($array['type'])->toBe('Edm.String');
        expect($array['nullable'])->toBeFalse();
        expect($array['is_key'])->toBeTrue();
        expect($array['is_udf'])->toBeFalse();
        expect($array['max_length'])->toBe(50);
    });

    it('converts to array with null max length', function (): void {
        $field = new FieldInfo(
            name: 'DocTotal',
            type: 'Edm.Double',
        );

        $array = $field->toArray();

        expect($array['max_length'])->toBeNull();
        expect($array['description'])->toBeNull();
        expect($array['valid_values'])->toBeNull();
    });

    it('checks if field is string type', function (): void {
        $stringField = new FieldInfo('Name', 'Edm.String');
        $intField = new FieldInfo('Count', 'Edm.Int32');

        expect($stringField->isString())->toBeTrue();
        expect($intField->isString())->toBeFalse();
    });

    it('checks if field is numeric type', function (): void {
        $intField = new FieldInfo('Count', 'Edm.Int32');
        $doubleField = new FieldInfo('Amount', 'Edm.Double');
        $stringField = new FieldInfo('Name', 'Edm.String');

        expect($intField->isNumeric())->toBeTrue();
        expect($doubleField->isNumeric())->toBeTrue();
        expect($stringField->isNumeric())->toBeFalse();
    });

    it('checks if field is date type', function (): void {
        $dateField = new FieldInfo('CreateDate', 'Edm.DateTime');
        $stringField = new FieldInfo('Name', 'Edm.String');

        expect($dateField->isDate())->toBeTrue();
        expect($stringField->isDate())->toBeFalse();
    });

    it('checks if field is boolean type', function (): void {
        $boolField = new FieldInfo('IsActive', 'Edm.Boolean');
        $stringField = new FieldInfo('Name', 'Edm.String');

        expect($boolField->isBoolean())->toBeTrue();
        expect($stringField->isBoolean())->toBeFalse();
    });

    it('implements Arrayable interface', function (): void {
        $field = new FieldInfo('CardCode', 'Edm.String');

        expect($field)->toBeInstanceOf(\Illuminate\Contracts\Support\Arrayable::class);
    });

    it('has readonly properties', function (): void {
        $field = new FieldInfo('CardCode', 'Edm.String');

        $reflection = new ReflectionClass($field);
        $nameProperty = $reflection->getProperty('name');

        expect($nameProperty->isReadOnly())->toBeTrue();
    });
});
