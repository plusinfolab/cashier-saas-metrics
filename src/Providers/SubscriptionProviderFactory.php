<?php

namespace PlusInfoLab\CashierSaaSMetrics\Providers;

use Illuminate\Support\Manager;
use PlusInfoLab\CashierSaaSMetrics\Contracts\SubscriptionProvider;

class SubscriptionProviderFactory extends Manager
{
    /**
     * Get the default subscription provider driver name.
     */
    public function getDefaultDriver(): string
    {
        return config('saas-metrics.provider', 'stripe');
    }

    /**
     * Create a Stripe provider instance.
     */
    protected function createStripeDriver(): SubscriptionProvider
    {
        return new Adapters\StripeProvider(
            $this->config->get('saas-metrics.providers.stripe', []),
            $this->config->get('saas-metrics.base_currency', 'USD')
        );
    }

    /**
     * Create a Paddle provider instance.
     */
    protected function createPaddleDriver(): SubscriptionProvider
    {
        return new Adapters\PaddleProvider(
            $this->config->get('saas-metrics.providers.paddle', []),
            $this->config->get('saas-metrics.base_currency', 'USD')
        );
    }

    /**
     * Create a Custom provider instance.
     */
    protected function createCustomDriver(): SubscriptionProvider
    {
        return new Adapters\CustomProvider(
            $this->config->get('saas-metrics.providers.custom', []),
            $this->config->get('saas-metrics.base_currency', 'USD')
        );
    }

    /**
     * Create a custom driver instance.
     */
    public function createDriver($driver)
    {
        // First check if there's a custom creator
        if (isset($this->customCreators[$driver])) {
            return $this->customCreators[$driver]();
        }

        // Then try to create from config
        $customProviderClass = $this->config->get("saas-metrics.providers.{$driver}.driver");

        if ($customProviderClass && class_exists($customProviderClass)) {
            return new $customProviderClass(
                $this->config->get("saas-metrics.providers.{$driver}", []),
                $this->config->get('saas-metrics.base_currency', 'USD')
            );
        }

        // Finally, fall back to parent behavior
        return parent::createDriver($driver);
    }
}
