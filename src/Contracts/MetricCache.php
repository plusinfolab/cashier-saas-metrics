<?php

namespace PlusInfoLab\CashierSaaSMetrics\Contracts;

interface MetricCache
{
    /**
     * Get a cached metric value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a cached metric value with tags.
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $ttl
     * @param array $tags
     * @return bool
     */
    public function set(string $key, mixed $value, ?int $ttl = null, array $tags = []): bool;

    /**
     * Forget a cached metric.
     *
     * @param string $key
     * @return bool
     */
    public function forget(string $key): bool;

    /**
     * Clear cache by tags.
     *
     * @param array $tags
     * @return bool
     */
    public function clearByTags(array $tags): bool;

    /**
     * Clear all metric cache.
     *
     * @return bool
     */
    public function clear(): bool;

    /**
     * Remember a value if not cached.
     *
     * @param string $key
     * @param int|null $ttl
     * @param callable $callback
     * @param array $tags
     * @return mixed
     */
    public function remember(string $key, ?int $ttl, callable $callback, array $tags = []): mixed;
}
