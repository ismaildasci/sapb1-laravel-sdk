<?php

declare(strict_types=1);

namespace SapB1\Metadata;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Represents the schema of a SAP B1 entity.
 *
 * @implements Arrayable<string, mixed>
 */
class EntitySchema implements Arrayable
{
    /**
     * @param  array<string, FieldInfo>  $fields
     * @param  array<string, FieldInfo>  $userDefinedFields
     * @param  array<string, string>  $navigationProperties
     */
    public function __construct(
        public readonly string $name,
        public readonly string $entityType,
        public readonly array $fields,
        public readonly array $userDefinedFields = [],
        public readonly array $navigationProperties = [],
        public readonly ?string $keyField = null,
        public readonly bool $isUdo = false,
    ) {}

    /**
     * Check if entity has a specific field.
     */
    public function hasField(string $name): bool
    {
        return isset($this->fields[$name]) || isset($this->userDefinedFields[$name]);
    }

    /**
     * Get a field by name.
     */
    public function getField(string $name): ?FieldInfo
    {
        return $this->fields[$name] ?? $this->userDefinedFields[$name] ?? null;
    }

    /**
     * Get all UDF field names.
     *
     * @return array<int, string>
     */
    public function getUdfNames(): array
    {
        return array_keys($this->userDefinedFields);
    }

    /**
     * Check if entity has any UDFs.
     */
    public function hasUdfs(): bool
    {
        return ! empty($this->userDefinedFields);
    }

    /**
     * Get all field names including UDFs.
     *
     * @return array<int, string>
     */
    public function getAllFieldNames(): array
    {
        return array_merge(
            array_keys($this->fields),
            array_keys($this->userDefinedFields)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'entity_type' => $this->entityType,
            'key_field' => $this->keyField,
            'is_udo' => $this->isUdo,
            'fields' => array_map(fn (FieldInfo $f) => $f->toArray(), $this->fields),
            'user_defined_fields' => array_map(fn (FieldInfo $f) => $f->toArray(), $this->userDefinedFields),
            'navigation_properties' => $this->navigationProperties,
        ];
    }
}
