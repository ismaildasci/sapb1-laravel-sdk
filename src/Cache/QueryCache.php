<?php

declare(strict_types=1);

namespace SapB1\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class QueryCache
{
    protected string $prefix;

    protected int $ttl;

    protected bool $enabled;

    /**
     * @var array<int, string>
     */
    protected array $includes;

    /**
     * @var array<int, string>
     */
    protected array $excludes;

    /**
     * Create a new QueryCache instance.
     */
    public function __construct()
    {
        $this->enabled = (bool) config('sap-b1.cache.enabled', false);
        $this->prefix = (string) config('sap-b1.cache.prefix', 'sap_b1_cache:');
        $this->ttl = (int) config('sap-b1.cache.ttl', 300);

        /** @var array<int, string> $includes */
        $includes = config('sap-b1.cache.include', []);
        $this->includes = $includes;

        /** @var array<int, string> $excludes */
        $excludes = config('sap-b1.cache.exclude', ['Login', 'Logout']);
        $this->excludes = $excludes;
    }

    /**
     * Check if caching is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable caching.
     */
    public function enable(): self
    {
        $this->enabled = true;

        return $this;
    }

    /**
     * Disable caching.
     */
    public function disable(): self
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * Check if an endpoint should be cached.
     */
    public function shouldCache(string $endpoint): bool
    {
        if (! $this->enabled) {
            return false;
        }

        // Check excludes first
        foreach ($this->excludes as $pattern) {
            if ($this->matchesPattern($endpoint, $pattern)) {
                return false;
            }
        }

        // If includes is empty, cache everything not excluded
        if (empty($this->includes)) {
            return true;
        }

        // Check if endpoint matches any include pattern
        foreach ($this->includes as $pattern) {
            if ($this->matchesPattern($endpoint, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get a cached response.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $connection, string $method, string $endpoint, ?string $query = null): ?array
    {
        if (! $this->enabled || $method !== 'GET') {
            return null;
        }

        $key = $this->buildKey($connection, $endpoint, $query);

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($key);

        return $cached;
    }

    /**
     * Store a response in cache.
     *
     * @param  array<string, mixed>  $response
     */
    public function put(string $connection, string $method, string $endpoint, ?string $query, array $response, ?int $ttl = null): void
    {
        if (! $this->enabled || $method !== 'GET') {
            return;
        }

        if (! $this->shouldCache($endpoint)) {
            return;
        }

        $key = $this->buildKey($connection, $endpoint, $query);
        $ttl = $ttl ?? $this->ttl;

        Cache::put($key, $response, $ttl);
    }

    /**
     * Forget a cached response.
     */
    public function forget(string $connection, string $endpoint, ?string $query = null): bool
    {
        $key = $this->buildKey($connection, $endpoint, $query);

        return Cache::forget($key);
    }

    /**
     * Flush all cache for a connection.
     */
    public function flush(string $connection): bool
    {
        // Note: This requires the cache driver to support tags
        // For simple implementation, we'll use a pattern-based approach
        $pattern = $this->prefix.$connection.':*';

        // For Redis
        if (config('cache.default') === 'redis') {
            return $this->flushByPattern($pattern);
        }

        // For other drivers, we can't easily flush by pattern
        // Consider using cache tags in production

        return false;
    }

    /**
     * Flush cache for a specific endpoint (all queries).
     */
    public function flushEndpoint(string $connection, string $endpoint): bool
    {
        $pattern = $this->prefix.$connection.':'.$endpoint.':*';

        if (config('cache.default') === 'redis') {
            return $this->flushByPattern($pattern);
        }

        return false;
    }

    /**
     * Flush cache by pattern (Redis only).
     */
    protected function flushByPattern(string $pattern): bool
    {
        try {
            /** @var array<int, string>|false $keys */
            $keys = Redis::keys($pattern);

            if ($keys !== false && ! empty($keys)) {
                Redis::del(...$keys);

                return true;
            }
        } catch (\Exception) {
            // Redis not available or command failed
        }

        return false;
    }

    /**
     * Build a cache key.
     */
    protected function buildKey(string $connection, string $endpoint, ?string $query): string
    {
        $key = $this->prefix.$connection.':'.$endpoint;

        if ($query !== null) {
            $key .= ':'.md5($query);
        }

        return $key;
    }

    /**
     * Check if an endpoint matches a pattern.
     */
    protected function matchesPattern(string $endpoint, string $pattern): bool
    {
        // Support wildcards
        if (str_contains($pattern, '*')) {
            $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).'$/';

            return (bool) preg_match($regex, $endpoint);
        }

        return $endpoint === $pattern || str_starts_with($endpoint, $pattern.'(');
    }

    /**
     * Set the TTL for cache entries.
     */
    public function setTtl(int $seconds): self
    {
        $this->ttl = $seconds;

        return $this;
    }

    /**
     * Get the current TTL.
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }

    /**
     * Add an endpoint to the include list.
     */
    public function include(string $pattern): self
    {
        $this->includes[] = $pattern;

        return $this;
    }

    /**
     * Add an endpoint to the exclude list.
     */
    public function exclude(string $pattern): self
    {
        $this->excludes[] = $pattern;

        return $this;
    }
}
