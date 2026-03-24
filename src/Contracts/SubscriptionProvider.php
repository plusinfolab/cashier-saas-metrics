<?php

namespace PlusInfoLab\CashierSaaSMetrics\Contracts;

use Illuminate\Support\Collection;

interface SubscriptionProvider
{
    /**
     * Get all active subscriptions.
     *
     * @return Collection<int, array>
     */
    public function getActiveSubscriptions(): Collection;

    /**
     * Get all cancelled subscriptions within a period.
     *
     * @param \DateTimeInterface $start
     * @param \DateTimeInterface $end
     * @return Collection<int, array>
     */
    public function getCancelledSubscriptions(\DateTimeInterface $start, \DateTimeInterface $end): Collection;

    /**
     * Get all new subscriptions within a period.
     *
     * @param \DateTimeInterface $start
     * @param \DateTimeInterface $end
     * @return Collection<int, array>
     */
    public function getNewSubscriptions(\DateTimeInterface $start, \DateTimeInterface $end): Collection;

    /**
     * Get subscription by ID.
     *
     * @param string|int $subscriptionId
     * @return array|null
     */
    public function getSubscription(string|int $subscriptionId): ?array;

    /**
     * Get all payments/invoices within a period.
     *
     * @param \DateTimeInterface $start
     * @param \DateTimeInterface $end
     * @return Collection<int, array>
     */
    public function getPayments(\DateTimeInterface $start, \DateTimeInterface $end): Collection;

    /**
     * Get payments for a specific subscription.
     *
     * @param string|int $subscriptionId
     * @return Collection<int, array>
     */
    public function getSubscriptionPayments(string|int $subscriptionId): Collection;

    /**
     * Get the subscription amount in the base currency.
     *
     * @param array $subscription
     * @return float
     */
    public function getSubscriptionAmount(array $subscription): float;

    /**
     * Get the subscription interval (monthly, yearly, etc.).
     *
     * @param array $subscription
     * @return string
     */
    public function getSubscriptionInterval(array $subscription): string;

    /**
     * Get the subscription currency code.
     *
     * @param array $subscription
     * @return string
     */
    public function getSubscriptionCurrency(array $subscription): string;

    /**
     * Get the subscription status.
     *
     * @param array $subscription
     * @return string
     */
    public function getSubscriptionStatus(array $subscription): string;

    /**
     * Get the subscription created date.
     *
     * @param array $subscription
     * @return \DateTimeInterface
     */
    public function getSubscriptionCreatedAt(array $subscription): \DateTimeInterface;

    /**
     * Get the subscription cancelled date.
     *
     * @param array $subscription
     * @return \DateTimeInterface|null
     */
    public function getSubscriptionCancelledAt(array $subscription): ?\DateTimeInterface;

    /**
     * Check if subscription is active.
     *
     * @param array $subscription
     * @return bool
     */
    public function isActive(array $subscription): bool;

    /**
     * Check if subscription is cancelled.
     *
     * @param array $subscription
     * @return bool
     */
    public function isCancelled(array $subscription): bool;

    /**
     * Check if subscription is on trial.
     *
     * @param array $subscription
     * @return bool
     */
    public function isOnTrial(array $subscription): bool;

    /**
     * Get custom attributes from subscription.
     *
     * @param array $subscription
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getSubscriptionAttribute(array $subscription, string $key, mixed $default = null): mixed;

    /**
     * Convert amount from one currency to base currency.
     *
     * @param float $amount
     * @param string $fromCurrency
     * @return float
     */
    public function convertToBaseCurrency(float $amount, string $fromCurrency): float;
}
