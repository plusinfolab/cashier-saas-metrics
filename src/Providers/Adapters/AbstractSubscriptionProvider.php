<?php

namespace PlusInfoLab\CashierSaaSMetrics\Providers\Adapters;

use Illuminate\Support\Collection;
use PlusInfoLab\CashierSaaSMetrics\Contracts\SubscriptionProvider as Contract;

abstract class AbstractSubscriptionProvider implements Contract
{
    public function __construct(
        protected readonly array $config,
        protected readonly string $baseCurrency
    ) {
    }

    /**
     * Get all active subscriptions.
     */
    abstract public function getActiveSubscriptions(): Collection;

    /**
     * Get all cancelled subscriptions within a period.
     */
    abstract public function getCancelledSubscriptions(\DateTimeInterface $start, \DateTimeInterface $end): Collection;

    /**
     * Get all new subscriptions within a period.
     */
    abstract public function getNewSubscriptions(\DateTimeInterface $start, \DateTimeInterface $end): Collection;

    /**
     * Get subscription by ID.
     */
    abstract public function getSubscription(string|int $subscriptionId): ?array;

    /**
     * Get all payments/invoices within a period.
     */
    abstract public function getPayments(\DateTimeInterface $start, \DateTimeInterface $end): Collection;

    /**
     * Get payments for a specific subscription.
     */
    abstract public function getSubscriptionPayments(string|int $subscriptionId): Collection;

    /**
     * Get the subscription amount in the base currency.
     */
    public function getSubscriptionAmount(array $subscription): float
    {
        return (float) ($subscription['amount'] ?? $subscription['plan_amount'] ?? 0);
    }

    /**
     * Get the subscription interval.
     */
    public function getSubscriptionInterval(array $subscription): string
    {
        return $subscription['interval'] ?? $subscription['plan_interval'] ?? 'monthly';
    }

    /**
     * Get the subscription currency code.
     */
    public function getSubscriptionCurrency(array $subscription): string
    {
        return strtoupper($subscription['currency'] ?? $subscription['plan_currency'] ?? $this->baseCurrency);
    }

    /**
     * Get the subscription status.
     */
    public function getSubscriptionStatus(array $subscription): string
    {
        return strtolower($subscription['status'] ?? 'unknown');
    }

    /**
     * Get the subscription created date.
     */
    public function getSubscriptionCreatedAt(array $subscription): \DateTimeInterface
    {
        $createdAt = $subscription['created_at'] ?? $subscription['created'] ?? now();

        if ($createdAt instanceof \DateTimeInterface) {
            return $createdAt;
        }

        return \Illuminate\Support\Carbon::parse($createdAt);
    }

    /**
     * Get the subscription cancelled date.
     */
    public function getSubscriptionCancelledAt(array $subscription): ?\DateTimeInterface
    {
        $cancelledAt = $subscription['cancelled_at'] ?? $subscription['canceled_at'] ?? null;

        if ($cancelledAt === null) {
            return null;
        }

        if ($cancelledAt instanceof \DateTimeInterface) {
            return $cancelledAt;
        }

        return \Illuminate\Support\Carbon::parse($cancelledAt);
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(array $subscription): bool
    {
        $status = $this->getSubscriptionStatus($subscription);

        return in_array($status, ['active', 'trialing'], true);
    }

    /**
     * Check if subscription is cancelled.
     */
    public function isCancelled(array $subscription): bool
    {
        return $this->getSubscriptionStatus($subscription) === 'cancelled' ||
               $this->getSubscriptionCancelledAt($subscription) !== null;
    }

    /**
     * Check if subscription is on trial.
     */
    public function isOnTrial(array $subscription): bool
    {
        return $this->getSubscriptionStatus($subscription) === 'trialing';
    }

    /**
     * Get custom attributes from subscription.
     */
    public function getSubscriptionAttribute(array $subscription, string $key, mixed $default = null): mixed
    {
        return $subscription[$key] ?? $subscription['metadata'][$key] ?? $subscription['attributes'][$key] ?? $default;
    }

    /**
     * Convert amount from one currency to base currency.
     * Override this in your provider to use real exchange rates.
     */
    public function convertToBaseCurrency(float $amount, string $fromCurrency): float
    {
        // If same currency, no conversion needed
        if (strtoupper($fromCurrency) === strtoupper($this->baseCurrency)) {
            return $amount;
        }

        // Try to get exchange rate from config
        $rates = config('saas-metrics.exchange_rates', []);

        if (isset($rates[$fromCurrency])) {
            return $amount * $rates[$fromCurrency];
        }

        // Default: return as-is (you should implement real currency conversion)
        return $amount;
    }

    /**
     * Make an API request (helper method).
     */
    protected function makeRequest(string $method, string $url, array $options = []): array
    {
        $client = new \GuzzleHttp\Client(array_merge([
            'base_uri' => $this->getApiBaseUrl(),
            'headers' => $this->getApiHeaders(),
        ], $options));

        $response = $client->request($method, $url, $options);

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * Get the API base URL.
     */
    protected function getApiBaseUrl(): string
    {
        return $this->config['api_url'] ?? '';
    }

    /**
     * Get the API headers.
     */
    protected function getApiHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->config['api_key'] ?? '',
            'Content-Type' => 'application/json',
        ];
    }
}
