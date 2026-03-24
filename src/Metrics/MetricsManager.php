<?php

namespace PlusInfoLab\CashierSaaSMetrics\Metrics;

use Illuminate\Support\Collection;
use PlusInfoLab\CashierSaaSMetrics\Cache\MetricsCache;
use PlusInfoLab\CashierSaaSMetrics\Contracts\MetricCalculator;
use PlusInfoLab\CashierSaaSMetrics\Contracts\SubscriptionProvider;
use PlusInfoLab\CashierSaaSMetrics\Providers\SubscriptionProviderFactory;
use PlusInfoLab\CashierSaaSMetrics\Support\MetricResult;

class MetricsManager
{
    protected SubscriptionProvider $provider;

    protected MetricsCache $cache;

    protected string $baseCurrency;

    public function __construct(
        protected SubscriptionProviderFactory $providerFactory,
        protected array $config
    ) {
        $this->baseCurrency = $config['base_currency'] ?? 'USD';
    }

    /**
     * Get the subscription provider.
     */
    public function getProvider(): SubscriptionProvider
    {
        if (! isset($this->provider)) {
            $this->provider = $this->providerFactory->driver();
        }

        return $this->provider;
    }

    /**
     * Get the metrics cache instance.
     */
    public function getCache(): MetricsCache
    {
        if (! isset($this->cache)) {
            $this->cache = new MetricsCache(app('cache')->store());
        }

        return $this->cache;
    }

    /**
     * Get a metric calculator instance.
     */
    public function getCalculator(string $metric): MetricCalculator
    {
        $provider = $this->getProvider();
        $cache = $this->getCache();

        return match ($metric) {
            'mrr' => new MRR($provider, $this->baseCurrency),
            'churn_rate', 'churn' => new ChurnRate($provider, $this->baseCurrency),
            'ltv', 'lifetime_value' => new LifetimeValue($provider, $this->baseCurrency),
            'arpu' => new ARPU($provider, $this->baseCurrency),
            'cohort', 'cohorts', 'cohort_analysis' => new CohortAnalysis($provider, $this->baseCurrency),
            default => throw new \InvalidArgumentException("Unknown metric: {$metric}"),
        };
    }

    /**
     * Get MRR calculator.
     */
    public function mrr(): MRR
    {
        return $this->getCalculator('mrr');
    }

    /**
     * Get Churn Rate calculator.
     */
    public function churnRate(): ChurnRate
    {
        return $this->getCalculator('churn_rate');
    }

    /**
     * Get Churn Rate calculator (alias).
     */
    public function churn(): ChurnRate
    {
        return $this->churnRate();
    }

    /**
     * Get Lifetime Value calculator.
     */
    public function lifetimeValue(): LifetimeValue
    {
        return $this->getCalculator('ltv');
    }

    /**
     * Get Lifetime Value calculator (alias).
     */
    public function ltv(): LifetimeValue
    {
        return $this->lifetimeValue();
    }

    /**
     * Get ARPU calculator.
     */
    public function arpu(): ARPU
    {
        return $this->getCalculator('arpu');
    }

    /**
     * Get Cohort Analysis calculator.
     */
    public function cohorts(): CohortAnalysis
    {
        return $this->getCalculator('cohort');
    }

    /**
     * Get Cohort Analysis calculator (alias).
     */
    public function cohortAnalysis(): CohortAnalysis
    {
        return $this->cohorts();
    }

    /**
     * Calculate a metric with caching.
     */
    public function calculate(string $metric, ?array $options = null): MetricResult
    {
        $calculator = $this->getCalculator($metric);

        if ($options !== null) {
            foreach ($options as $method => $value) {
                if (method_exists($calculator, $method)) {
                    $calculator->$method($value);
                }
            }
        }

        $cache = $this->getCache();
        $cacheKey = $calculator->getCacheKey();
        $cacheTTL = $calculator->getCacheTTL();

        return $cache->remember(
            $cacheKey,
            $cacheTTL,
            function () use ($calculator) {
                return $calculator->calculate();
            },
            [$metric]
        );
    }

    /**
     * Calculate multiple metrics at once.
     */
    public function calculateMultiple(array $metrics, ?array $options = null): Collection
    {
        return collect($metrics)->mapWithKeys(function ($metric) use ($options) {
            return [$metric => $this->calculate($metric, $options)];
        });
    }

    /**
     * Get all metrics dashboard data.
     */
    public function dashboard(?string $period = null): array
    {
        $periodOptions = $period ? ['period' => $period] : null;

        return [
            'mrr' => $this->calculate('mrr', $periodOptions),
            'churn_rate' => $this->calculate('churn_rate', $periodOptions),
            'ltv' => $this->calculate('ltv', $periodOptions),
            'arpu' => $this->calculate('arpu', $periodOptions),
            'period' => $period ?? 'this_month',
            'currency' => $this->baseCurrency,
        ];
    }

    /**
     * Clear all metric cache.
     */
    public function clearCache(): bool
    {
        return $this->getCache()->clear();
    }

    /**
     * Clear cache for specific metric.
     */
    public function clearMetricCache(string $metric): bool
    {
        return $this->getCache()->clearByTags([$metric]);
    }
}
