<?php

declare(strict_types=1);

namespace SapB1\Cache;

class CacheInvalidator
{
    protected QueryCache $cache;

    /**
     * Mapping of entities to related entities that should be invalidated.
     *
     * @var array<string, array<int, string>>
     */
    protected array $relations = [
        'Orders' => ['BusinessPartners', 'Items', 'PriceLists'],
        'Invoices' => ['BusinessPartners', 'Items', 'Orders'],
        'DeliveryNotes' => ['BusinessPartners', 'Items', 'Orders'],
        'PurchaseOrders' => ['BusinessPartners', 'Items'],
        'Items' => ['ItemGroups', 'Warehouses', 'PriceLists'],
        'BusinessPartners' => ['BusinessPartnerGroups', 'SalesPersons'],
    ];

    /**
     * Create a new CacheInvalidator instance.
     */
    public function __construct(QueryCache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Invalidate cache for an endpoint after a write operation.
     */
    public function invalidate(string $connection, string $method, string $endpoint): void
    {
        if (! $this->cache->isEnabled()) {
            return;
        }

        // Only invalidate on write operations
        if (! in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            return;
        }

        // Extract base endpoint (remove key part)
        $baseEndpoint = $this->extractBaseEndpoint($endpoint);

        // Invalidate the endpoint itself
        $this->cache->flushEndpoint($connection, $baseEndpoint);

        // Invalidate related endpoints
        $this->invalidateRelated($connection, $baseEndpoint);
    }

    /**
     * Invalidate related endpoints.
     */
    protected function invalidateRelated(string $connection, string $endpoint): void
    {
        $related = $this->relations[$endpoint] ?? [];

        foreach ($related as $relatedEndpoint) {
            $this->cache->flushEndpoint($connection, $relatedEndpoint);
        }

        // Also check if this endpoint is related to others
        foreach ($this->relations as $parent => $children) {
            if (in_array($endpoint, $children, true)) {
                $this->cache->flushEndpoint($connection, $parent);
            }
        }
    }

    /**
     * Extract the base endpoint from a full endpoint path.
     */
    protected function extractBaseEndpoint(string $endpoint): string
    {
        // Remove key part: Orders(123) -> Orders
        if (preg_match('/^([A-Za-z]+)/', $endpoint, $matches)) {
            return $matches[1];
        }

        return $endpoint;
    }

    /**
     * Add a custom relation for invalidation.
     *
     * @param  array<int, string>  $related
     */
    public function addRelation(string $endpoint, array $related): self
    {
        $this->relations[$endpoint] = array_merge(
            $this->relations[$endpoint] ?? [],
            $related
        );

        return $this;
    }

    /**
     * Set the relations map.
     *
     * @param  array<string, array<int, string>>  $relations
     */
    public function setRelations(array $relations): self
    {
        $this->relations = $relations;

        return $this;
    }

    /**
     * Get the relations map.
     *
     * @return array<string, array<int, string>>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Manually invalidate all cache for a connection.
     */
    public function invalidateAll(string $connection): bool
    {
        return $this->cache->flush($connection);
    }

    /**
     * Manually invalidate specific endpoints.
     *
     * @param  array<int, string>  $endpoints
     */
    public function invalidateEndpoints(string $connection, array $endpoints): void
    {
        foreach ($endpoints as $endpoint) {
            $this->cache->flushEndpoint($connection, $endpoint);
        }
    }
}
