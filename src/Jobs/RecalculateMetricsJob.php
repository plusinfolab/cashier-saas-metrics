<?php

namespace PlusInfoLab\CashierSaaSMetrics\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PlusInfoLab\CashierSaaSMetrics\Cache\MetricsCache;
use PlusInfoLab\CashierSaaSMetrics\Contracts\SubscriptionProvider;

class RecalculateMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Indicate if the job should be marked as failed on timeout.
     */
    public bool $failOnTimeout = true;

    public function __construct(
        public readonly string|int $subscriptionId,
        public readonly string $event = 'updated',
        public readonly ?array $affectedMetrics = null
    ) {
        $this->onQueue(config('saas-metrics.queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(
        SubscriptionProvider $provider,
        MetricsCache $cache
    ): void {
        $tags = ['metrics'];

        // Clear all metric cache or specific tags
        if ($this->affectedMetrics !== null) {
            $tags = array_merge($tags, $this->affectedMetrics);
        }

        $cache->clearByTags($tags);

        // Optionally recalculate and cache key metrics
        if (config('saas-metrics.cache.precalculate', false)) {
            $this->precalculateKeyMetrics($provider, $cache);
        }
    }

    /**
     * Precalculate key metrics after cache invalidation.
     */
    protected function precalculateKeyMetrics(
        SubscriptionProvider $provider,
        MetricsCache $cache
    ): void {
        $metricsToPrecalculate = config('saas-metrics.cache.precalculate_metrics', [
            'mrr',
            'churn_rate',
        ]);

        foreach ($metricsToPrecalculate as $metric) {
            try {
                // Dispatch a job to recalculate each metric
                dispatch(new CalculateMetricJob($metric));
            } catch (\Exception $e) {
                report($e);
            }
        }
    }

    /**
     * Get the tags the job should be monitored with.
     */
    public function tags(): array
    {
        return [
            'saas-metrics',
            'recalculate:'.$this->event,
            'subscription:'.$this->subscriptionId,
        ];
    }
}
