<?php

namespace PlusInfoLab\CashierSaaSMetrics;

use Illuminate\Support\Facades\Event;
use PlusInfoLab\CashierSaaSMetrics\Jobs\RecalculateMetricsJob;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CashierSaaSMetricsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('cashier-saas-metrics')
            ->hasConfigFile()
            ->hasViews()
            ->hasViewComponents('saas-metrics')
            ->hasMigration('create_metric_snapshots_table');
    }

    public function packageRegistered(): void
    {
        // Register the subscription provider factory
        $this->app->singleton('saas-metrics.provider', function ($app) {
            return new Providers\SubscriptionProviderFactory($app);
        });

        // Register the metrics manager
        $this->app->singleton('saas-metrics', function ($app) {
            return new Metrics\MetricsManager(
                $app->make('saas-metrics.provider'),
                config('saas-metrics', [])
            );
        });

        // Register the metrics cache
        $this->app->singleton('saas-metrics.cache', function ($app) {
            return new Cache\MetricsCache($app['cache']->store());
        });
    }

    public function packageBooted(): void
    {
        // Register event listeners for automatic cache invalidation
        $this->registerEventListeners();
    }

    /**
     * Register event listeners for metric recalculation.
     */
    protected function registerEventListeners(): void
    {
        $autoRecalculate = config('saas-metrics.auto_recalculate', true);

        if (! $autoRecalculate) {
            return;
        }

        Event::listen(function (Events\SubscriptionCreated $event) {
            RecalculateMetricsJob::dispatch(
                $event->subscriptionId,
                'created',
                ['mrr']
            );
        });

        Event::listen(function (Events\SubscriptionCancelled $event) {
            RecalculateMetricsJob::dispatch(
                $event->subscriptionId,
                'cancelled',
                ['mrr', 'churn_rate', 'ltv']
            );
        });

        Event::listen(function (Events\SubscriptionUpdated $event) {
            $affectedMetrics = $this->determineAffectedMetrics($event->changes ?? []);

            RecalculateMetricsJob::dispatch(
                $event->subscriptionId,
                'updated',
                $affectedMetrics
            );
        });
    }

    /**
     * Determine which metrics are affected by subscription changes.
     */
    protected function determineAffectedMetrics(array $changes): array
    {
        $affectedMetrics = ['mrr'];

        if (isset($changes['status']) || isset($changes['cancelled_at'])) {
            $affectedMetrics[] = 'churn_rate';
            $affectedMetrics[] = 'ltv';
        }

        if (isset($changes['amount']) || isset($changes['plan_id'])) {
            $affectedMetrics[] = 'arpu';
            $affectedMetrics[] = 'ltv';
        }

        return $affectedMetrics;
    }
}
