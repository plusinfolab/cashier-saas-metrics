<?php

namespace PlusInfoLab\CashierSaaSMetrics\Facades;

use Illuminate\Support\Facades\Facade;
use PlusInfoLab\CashierSaaSMetrics\Contracts\MetricCalculator;
use PlusInfoLab\CashierSaaSMetrics\Contracts\SubscriptionProvider;

/**
 * @method static \PlusInfoLab\CashierSaaSMetrics\Metrics\MRR mrr()
 * @method static \PlusInfoLab\CashierSaaSMetrics\Metrics\ChurnRate churnRate()
 * @method static \PlusInfoLab\CashierSaaSMetrics\Metrics\ChurnRate churn()
 * @method static \PlusInfoLab\CashierSaaSMetrics\Metrics\LifetimeValue lifetimeValue()
 * @method static \PlusInfoLab\CashierSaaSMetrics\Metrics\LifetimeValue ltv()
 * @method static \PlusInfoLab\CashierSaaSMetrics\Metrics\ARPU arpu()
 * @method static \PlusInfoLab\CashierSaaSMetrics\Metrics\CohortAnalysis cohorts()
 * @method static \PlusInfoLab\CashierSaaSMetrics\Metrics\CohortAnalysis cohortAnalysis()
 *
 * @see \PlusInfoLab\CashierSaaSMetrics\Metrics\MetricsManager
 */
class Metrics extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'saas-metrics';
    }

    /**
     * Get a metric calculator instance.
     */
    public static function getCalculator(string $metric): MetricCalculator
    {
        return static::getFacadeRoot()->getCalculator($metric);
    }

    /**
     * Clear all metric cache.
     */
    public static function clearCache(): bool
    {
        return static::getFacadeRoot()->clearCache();
    }

    /**
     * Get the subscription provider.
     */
    public static function provider(): SubscriptionProvider
    {
        return static::getFacadeRoot()->getProvider();
    }
}
