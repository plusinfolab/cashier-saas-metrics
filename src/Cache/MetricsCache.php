<?php

namespace PlusInfoLab\CashierSaaSMetrics\Cache;

use Illuminate\Cache\Repository;
use PlusInfoLab\CashierSaaSMetrics\Contracts\MetricCache as Contract;

class MetricsCache implements Contract
{
    protected string $prefix = 'saas-metrics:';

    public function __construct(
        protected readonly Repository $cache
    ) {
    }

    /**
     * Get a cached metric value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($this->prefix.$key, $default);
    }

    /**
     * Set a cached metric value with tags.
     */
    public function set(string $key, mixed $value, ?int $ttl = null, array $tags = []): bool
    {
        $fullKey = $this->prefix.$key;
        $tags = array_merge($tags, ['saas-metrics']);

        if ($this->cache->supportsTags() && ! empty($tags)) {
            return $this->cache->tags($tags)->put($fullKey, $value, $ttl);
        }

        return $this->cache->put($fullKey, $value, $ttl);
    }

    /**
     * Forget a cached metric.
     */
    public function forget(string $key): bool
    {
        return $this->cache->forget($this->prefix.$key);
    }

    /**
     * Clear cache by tags.
     */
    public function clearByTags(array $tags): bool
    {
        if (! $this->cache->supportsTags()) {
            return $this->cache->flush();
        }

        $tags = array_map(fn ($tag) => $this->prefix.$tag, $tags);

        try {
            $this->cache->tags($tags)->flush();

            return true;
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Clear all metric cache.
     */
    public function clear(): bool
    {
        if ($this->cache->supportsTags()) {
            try {
                $this->cache->tags(['saas-metrics'])->flush();

                return true;
            } catch (\Exception $e) {
                report($e);

                return false;
            }
        }

        // Fallback: clear cache entries with our prefix
        $this->clearPrefix();

        return true;
    }

    /**
     * Remember a value if not cached.
     */
    public function remember(string $key, ?int $ttl, callable $callback, array $tags = []): mixed
    {
        $fullKey = $this->prefix.$key;
        $tags = array_merge($tags, ['saas-metrics']);

        if ($this->cache->supportsTags() && ! empty($tags)) {
            return $this->cache->tags($tags)->remember($fullKey, $ttl, $callback);
        }

        return $this->cache->remember($fullKey, $ttl, $callback);
    }

    /**
     * Remember a value forever if not cached.
     */
    public function rememberForever(string $key, callable $callback, array $tags = []): mixed
    {
        $fullKey = $this->prefix.$key;
        $tags = array_merge($tags, ['saas-metrics']);

        if ($this->cache->supportsTags() && ! empty($tags)) {
            return $this->cache->tags($tags)->rememberForever($fullKey, $callback);
        }

        return $this->cache->rememberForever($fullKey, $callback);
    }

    /**
     * Clear cache by prefix (fallback for non-tagged cache).
     */
    protected function clearPrefix(): bool
    {
        try {
            // This is a simple implementation that clears all cache
            // For production, you might want to use a more sophisticated approach
            // like iterating through cache keys or using a dedicated cache store
            return $this->cache->flush();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the cache key prefix.
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Set the cache key prefix.
     */
    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }
}
