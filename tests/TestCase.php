<?php

namespace PlusInfoLab\CashierSaaSMetrics\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use PlusInfoLab\CashierSaaSMetrics\CashierSaaSMetricsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'PlusInfoLab\\CashierSaaSMetrics\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            CashierSaaSMetricsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');

        // Set up package configuration
        $app['config']->set('saas-metrics.provider', 'custom');
        $app['config']->set('saas-metrics.base_currency', 'USD');
        $app['config']->set('saas-metrics.cache.enabled', false);
        $app['config']->set('saas-metrics.auto_recalculate', false);

        // Custom provider configuration for testing
        $app['config']->set('saas-metrics.providers.custom', [
            'models' => [
                'subscription' => null,
                'payment' => null,
            ],
        ]);
    }
}
