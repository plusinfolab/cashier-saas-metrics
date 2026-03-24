<?php

namespace PlusInfoLab\CashierSaaSMetrics\Contracts;

interface MetricCache
{
    /**
     * Get a cached metric value.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a cached metric value with tags.
     */
    public function set(string $key, mixed $value, ?int $ttl = null, array $tags = []): bool;

    /**
     * Forget a cached metric.
     */
    public function forget(string $key): bool;

    /**
     * Clear cache by tags.
     */
    public function clearByTags(array $tags): bool;

    /**
     * Clear all metric cache.
     */
    public function clear(): bool;

    /**
     * Remember a value if not cached.
     */
    public function remember(string $key, ?int $ttl, callable $callback, array $tags = []): mixed;
}
