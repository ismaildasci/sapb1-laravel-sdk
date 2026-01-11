<?php

declare(strict_types=1);

use SapB1\Metadata\EntitySchema;
use SapB1\Metadata\FieldInfo;

describe('EntitySchema', function (): void {
    it('creates entity schema with basic properties', function (): void {
        $schema = new EntitySchema(
            name: 'BusinessPartners',
            entityType: 'BusinessPartner',
            fields: [],
            userDefinedFields: [],
            navigationProperties: [],
            keyField: 'CardCode',
            isUdo: false,
        );

        expect($schema->name)->toBe('BusinessPartners');
        expect($schema->entityType)->toBe('BusinessPartner');
        expect($schema->keyField)->toBe('CardCode');
        expect($schema->isUdo)->toBeFalse();
    });

    it('checks if has field in standard fields', function (): void {
        $fields = [
            'CardCode' => new FieldInfo('CardCode', 'Edm.String', false, true, false),
            'CardName' => new FieldInfo('CardName', 'Edm.String', true, false, false),
        ];

        $schema = new EntitySchema(
            name: 'BusinessPartners',
            entityType: 'BusinessPartner',
            fields: $fields,
        );

        expect($schema->hasField('CardCode'))->toBeTrue();
        expect($schema->hasField('CardName'))->toBeTrue();
        expect($schema->hasField('NonExistent'))->toBeFalse();
    });

    it('checks if has field in user defined fields', function (): void {
        $udfs = [
            'U_CustomField' => new FieldInfo('U_CustomField', 'Edm.String', true, false, true),
        ];

        $schema = new EntitySchema(
            name: 'BusinessPartners',
            entityType: 'BusinessPartner',
            fields: [],
            userDefinedFields: $udfs,
        );

        expect($schema->hasField('U_CustomField'))->toBeTrue();
    });

    it('gets field from standard fields', function (): void {
        $fieldInfo = new FieldInfo('CardCode', 'Edm.String', false, true, false);
        $fields = ['CardCode' => $fieldInfo];

        $schema = new EntitySchema(
            name: 'BusinessPartners',
            entityType: 'BusinessPartner',
            fields: $fields,
        );

        expect($schema->getField('CardCode'))->toBe($fieldInfo);
    });

    it('gets field from user defined fields', function (): void {
        $fieldInfo = new FieldInfo('U_Custom', 'Edm.String', true, false, true);
        $udfs = ['U_Custom' => $fieldInfo];

        $schema = new EntitySchema(
            name: 'BusinessPartners',
            entityType: 'BusinessPartner',
            fields: [],
            userDefinedFields: $udfs,
        );

        expect($schema->getField('U_Custom'))->toBe($fieldInfo);
    });

    it('returns null for non-existent field', function (): void {
        $schema = new EntitySchema(
            name: 'BusinessPartners',
            entityType: 'BusinessPartner',
            fields: [],
        );

        expect($schema->getField('NonExistent'))->toBeNull();
    });

    it('gets UDF names', function (): void {
        $udfs = [
            'U_Field1' => new FieldInfo('U_Field1', 'Edm.String', true, false, true),
            'U_Field2' => new FieldInfo('U_Field2', 'Edm.Int32', true, false, true),
        ];

        $schema = new EntitySchema(
            name: 'BusinessPartners',
            entityType: 'BusinessPartner',
            fields: [],
            userDefinedFields: $udfs,
        );

        expect($schema->getUdfNames())->toBe(['U_Field1', 'U_Field2']);
    });

    it('checks if has UDFs', function (): void {
        $schemaWithoutUdfs = new EntitySchema(
            name: 'BusinessPartners',
            entityType: 'BusinessPartner',
            fields: [],
            userDefinedFields: [],
        );

        expect($schemaWithoutUdfs->hasUdfs())->toBeFalse();

        $schemaWithUdfs = new EntitySchema(
            name: 'BusinessPartners',
            entityType: 'BusinessPartner',
            fields: [],
            userDefinedFields: [
                'U_Custom' => new FieldInfo('U_Custom', 'Edm.String', true, false, true),
            ],
        );

        expect($schemaWithUdfs->hasUdfs())->toBeTrue();
    });

    it('gets all field names including UDFs', function (): void {
        $fields = [
            'CardCode' => new FieldInfo('CardCode', 'Edm.String', false, true, false),
            'CardName' => new FieldInfo('CardName', 'Edm.String', true, false, false),
        ];
        $udfs = [
            'U_Custom' => new FieldInfo('U_Custom', 'Edm.String', true, false, true),
        ];

        $schema = new EntitySchema(
            name: 'BusinessPartners',
            entityType: 'BusinessPartner',
            fields: $fields,
            userDefinedFields: $udfs,
        );

        $allFields = $schema->getAllFieldNames();

        expect($allFields)->toContain('CardCode');
        expect($allFields)->toContain('CardName');
        expect($allFields)->toContain('U_Custom');
        expect($allFields)->toHaveCount(3);
    });

    it('converts to array', function (): void {
        $fields = [
            'CardCode' => new FieldInfo('CardCode', 'Edm.String', false, true, false),
        ];
        $udfs = [
            'U_Custom' => new FieldInfo('U_Custom', 'Edm.String', true, false, true),
        ];
        $navProps = ['BPAddresses' => 'BusinessPartnerAddress'];

        $schema = new EntitySchema(
            name: 'BusinessPartners',
            entityType: 'BusinessPartner',
            fields: $fields,
            userDefinedFields: $udfs,
            navigationProperties: $navProps,
            keyField: 'CardCode',
            isUdo: false,
        );

        $array = $schema->toArray();

        expect($array)->toHaveKey('name');
        expect($array)->toHaveKey('entity_type');
        expect($array)->toHaveKey('key_field');
        expect($array)->toHaveKey('is_udo');
        expect($array)->toHaveKey('fields');
        expect($array)->toHaveKey('user_defined_fields');
        expect($array)->toHaveKey('navigation_properties');

        expect($array['name'])->toBe('BusinessPartners');
        expect($array['key_field'])->toBe('CardCode');
        expect($array['navigation_properties'])->toBe($navProps);
    });

    it('identifies UDO entities', function (): void {
        $udo = new EntitySchema(
            name: 'U_MyCustomObject',
            entityType: 'U_MyCustomObject',
            fields: [],
            isUdo: true,
        );

        expect($udo->isUdo)->toBeTrue();
    });

    it('implements Arrayable interface', function (): void {
        $schema = new EntitySchema(
            name: 'BusinessPartners',
            entityType: 'BusinessPartner',
            fields: [],
        );

        expect($schema)->toBeInstanceOf(\Illuminate\Contracts\Support\Arrayable::class);
    });
});
