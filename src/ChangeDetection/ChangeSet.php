<?php

declare(strict_types=1);

namespace SapB1\ChangeDetection;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class ChangeSet implements Arrayable
{
    /** @var array<string, array<string, mixed>> */
    protected array $created = [];

    /** @var array<string, array{record: array<string, mixed>, changes: array<string, array{from: mixed, to: mixed}>}> */
    protected array $updated = [];

    /** @var array<string, array<string, mixed>> */
    protected array $deleted = [];

    public function __construct(
        protected string $entity
    ) {}

    /**
     * Add a created record.
     *
     * @param  array<string, mixed>  $record
     */
    public function addCreated(string $key, array $record): self
    {
        $this->created[$key] = $record;

        return $this;
    }

    /**
     * Add an updated record.
     *
     * @param  array<string, mixed>  $record
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    public function addUpdated(string $key, array $record, array $changes = []): self
    {
        $this->updated[$key] = [
            'record' => $record,
            'changes' => $changes,
        ];

        return $this;
    }

    /**
     * Add a deleted record.
     *
     * @param  array<string, mixed>  $record
     */
    public function addDeleted(string $key, array $record): self
    {
        $this->deleted[$key] = $record;

        return $this;
    }

    /**
     * Get entity name.
     */
    public function getEntity(): string
    {
        return $this->entity;
    }

    /**
     * Get created records.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCreated(): array
    {
        return $this->created;
    }

    /**
     * Get updated records.
     *
     * @return array<string, array{record: array<string, mixed>, changes: array<string, array{from: mixed, to: mixed}>}>
     */
    public function getUpdated(): array
    {
        return $this->updated;
    }

    /**
     * Get deleted records.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDeleted(): array
    {
        return $this->deleted;
    }

    /**
     * Check if change set is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->created) && empty($this->updated) && empty($this->deleted);
    }

    /**
     * Get total number of changes.
     */
    public function count(): int
    {
        return count($this->created) + count($this->updated) + count($this->deleted);
    }

    /**
     * Check if there are created records.
     */
    public function hasCreated(): bool
    {
        return ! empty($this->created);
    }

    /**
     * Check if there are updated records.
     */
    public function hasUpdated(): bool
    {
        return ! empty($this->updated);
    }

    /**
     * Check if there are deleted records.
     */
    public function hasDeleted(): bool
    {
        return ! empty($this->deleted);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entity' => $this->entity,
            'created' => $this->created,
            'updated' => $this->updated,
            'deleted' => $this->deleted,
            'total_changes' => $this->count(),
        ];
    }
}
