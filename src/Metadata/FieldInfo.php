<?php

declare(strict_types=1);

namespace SapB1\Metadata;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Represents a field in a SAP B1 entity.
 *
 * @implements Arrayable<string, mixed>
 */
class FieldInfo implements Arrayable
{
    /**
     * @param  array<int|string, string>|null  $validValues
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly bool $nullable = true,
        public readonly bool $isKey = false,
        public readonly bool $isUdf = false,
        public readonly ?int $maxLength = null,
        public readonly ?string $description = null,
        public readonly ?array $validValues = null,
    ) {}

    /**
     * Check if this is a string type field.
     */
    public function isString(): bool
    {
        return in_array($this->type, ['Edm.String', 'string'], true);
    }

    /**
     * Check if this is a numeric type field.
     */
    public function isNumeric(): bool
    {
        return in_array($this->type, [
            'Edm.Int32',
            'Edm.Int64',
            'Edm.Double',
            'Edm.Decimal',
            'int',
            'double',
            'decimal',
        ], true);
    }

    /**
     * Check if this is a date/datetime type field.
     */
    public function isDate(): bool
    {
        return in_array($this->type, [
            'Edm.DateTime',
            'Edm.DateTimeOffset',
            'datetime',
        ], true);
    }

    /**
     * Check if this is a boolean type field.
     */
    public function isBoolean(): bool
    {
        return in_array($this->type, ['Edm.Boolean', 'boolean', 'bool'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'nullable' => $this->nullable,
            'is_key' => $this->isKey,
            'is_udf' => $this->isUdf,
            'max_length' => $this->maxLength,
            'description' => $this->description,
            'valid_values' => $this->validValues,
        ];
    }
}
