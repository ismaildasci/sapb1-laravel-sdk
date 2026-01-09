<?php

declare(strict_types=1);

namespace SapB1\ChangeDetection;

use Illuminate\Support\Facades\Cache;
use SapB1\Client\SapB1Client;

class ChangeDetector
{
    protected const CACHE_PREFIX = 'sap_b1_change_detection:';

    /**
     * @var array<string, EntityWatcher>
     */
    protected array $watchers = [];

    public function __construct(
        protected SapB1Client $client,
        protected string $connection = 'default'
    ) {}

    /**
     * Start watching an entity for changes.
     */
    public function watch(string $entity): EntityWatcher
    {
        if (! isset($this->watchers[$entity])) {
            $this->watchers[$entity] = new EntityWatcher($entity);
        }

        return $this->watchers[$entity];
    }

    /**
     * Stop watching an entity.
     */
    public function unwatch(string $entity): self
    {
        unset($this->watchers[$entity]);

        return $this;
    }

    /**
     * Poll all watched entities for changes.
     *
     * @return array<string, ChangeSet>
     */
    public function poll(): array
    {
        $changes = [];

        foreach ($this->watchers as $entity => $watcher) {
            $changeSet = $this->detectChanges($entity, $watcher);

            if (! $changeSet->isEmpty()) {
                $changes[$entity] = $changeSet;
                $this->dispatchCallbacks($watcher, $changeSet);
            }
        }

        return $changes;
    }

    /**
     * Poll a specific entity for changes.
     */
    public function pollEntity(string $entity): ChangeSet
    {
        $watcher = $this->watchers[$entity] ?? new EntityWatcher($entity);
        $changeSet = $this->detectChanges($entity, $watcher);

        if (! $changeSet->isEmpty() && isset($this->watchers[$entity])) {
            $this->dispatchCallbacks($watcher, $changeSet);
        }

        return $changeSet;
    }

    /**
     * Detect changes for an entity.
     */
    protected function detectChanges(string $entity, EntityWatcher $watcher): ChangeSet
    {
        $cacheKey = self::CACHE_PREFIX.$this->connection.':'.$entity;

        // Get last known state
        /** @var array<string, array<string, mixed>>|null $lastState */
        $lastState = Cache::get($cacheKey);

        // Fetch current state
        $currentState = $this->fetchEntityState($entity, $watcher);

        // First run - just store state, no changes
        if ($lastState === null) {
            Cache::put($cacheKey, $currentState, now()->addDay());

            return new ChangeSet($entity);
        }

        // Detect changes
        $changeSet = $this->compareStates($entity, $lastState, $currentState, $watcher);

        // Update stored state
        Cache::put($cacheKey, $currentState, now()->addDay());

        return $changeSet;
    }

    /**
     * Fetch current state of entity records.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function fetchEntityState(string $entity, EntityWatcher $watcher): array
    {
        $service = $this->client
            ->connection($this->connection)
            ->service($entity);

        // Apply key field selection
        $keyField = $watcher->getKeyField();
        $trackFields = $watcher->getTrackFields();

        $selectFields = array_unique(array_merge([$keyField], $trackFields, ['UpdateDate', 'UpdateTime']));
        $service->select($selectFields);

        // Apply filters
        if ($watcher->hasFilters()) {
            foreach ($watcher->getFilters() as $filter) {
                $service->where($filter['field'], $filter['operator'], $filter['value']);
            }
        }

        // Limit results for performance
        $service->top($watcher->getLimit());

        try {
            $result = $service->get();
        } catch (\Throwable) {
            return [];
        }

        // Index by key
        $state = [];
        $records = $result['value'] ?? $result;
        foreach ($records as $record) {
            $key = (string) ($record[$keyField] ?? '');
            if ($key !== '') {
                $state[$key] = $record;
            }
        }

        return $state;
    }

    /**
     * Compare two states to detect changes.
     *
     * @param  array<string, array<string, mixed>>  $lastState
     * @param  array<string, array<string, mixed>>  $currentState
     */
    protected function compareStates(
        string $entity,
        array $lastState,
        array $currentState,
        EntityWatcher $watcher
    ): ChangeSet {
        $changeSet = new ChangeSet($entity);
        $trackFields = $watcher->getTrackFields();

        // Detect created records
        foreach ($currentState as $key => $record) {
            if (! isset($lastState[$key])) {
                $changeSet->addCreated($key, $record);
            }
        }

        // Detect deleted records
        foreach ($lastState as $key => $record) {
            if (! isset($currentState[$key])) {
                $changeSet->addDeleted($key, $record);
            }
        }

        // Detect updated records
        foreach ($currentState as $key => $currentRecord) {
            if (isset($lastState[$key])) {
                $lastRecord = $lastState[$key];
                $changes = [];

                foreach ($trackFields as $field) {
                    $lastValue = $lastRecord[$field] ?? null;
                    $currentValue = $currentRecord[$field] ?? null;

                    if ($lastValue !== $currentValue) {
                        $changes[$field] = [
                            'from' => $lastValue,
                            'to' => $currentValue,
                        ];
                    }
                }

                // Also check UpdateDate/UpdateTime
                $lastUpdate = ($lastRecord['UpdateDate'] ?? '').($lastRecord['UpdateTime'] ?? '');
                $currentUpdate = ($currentRecord['UpdateDate'] ?? '').($currentRecord['UpdateTime'] ?? '');

                if ($lastUpdate !== $currentUpdate || ! empty($changes)) {
                    $changeSet->addUpdated($key, $currentRecord, $changes);
                }
            }
        }

        return $changeSet;
    }

    /**
     * Dispatch callbacks for detected changes.
     */
    protected function dispatchCallbacks(EntityWatcher $watcher, ChangeSet $changeSet): void
    {
        foreach ($changeSet->getCreated() as $key => $record) {
            foreach ($watcher->getCreatedCallbacks() as $callback) {
                $callback($record, $key);
            }
        }

        foreach ($changeSet->getUpdated() as $key => $data) {
            foreach ($watcher->getUpdatedCallbacks() as $callback) {
                $callback($data['record'], $data['changes'], $key);
            }
        }

        foreach ($changeSet->getDeleted() as $key => $record) {
            foreach ($watcher->getDeletedCallbacks() as $callback) {
                $callback($key, $record);
            }
        }
    }

    /**
     * Clear stored state for an entity.
     */
    public function clearState(string $entity): self
    {
        $cacheKey = self::CACHE_PREFIX.$this->connection.':'.$entity;
        Cache::forget($cacheKey);

        return $this;
    }

    /**
     * Clear all stored states.
     */
    public function clearAllStates(): self
    {
        foreach (array_keys($this->watchers) as $entity) {
            $this->clearState($entity);
        }

        return $this;
    }

    /**
     * Get all registered watchers.
     *
     * @return array<string, EntityWatcher>
     */
    public function getWatchers(): array
    {
        return $this->watchers;
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
}
