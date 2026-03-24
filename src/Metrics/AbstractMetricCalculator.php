<?php

namespace PlusInfoLab\CashierSaaSMetrics\Metrics;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use PlusInfoLab\CashierSaaSMetrics\Contracts\MetricCalculator as Contract;
use PlusInfoLab\CashierSaaSMetrics\Contracts\SubscriptionProvider;
use PlusInfoLab\CashierSaaSMetrics\Enums\PeriodType;
use PlusInfoLab\CashierSaaSMetrics\Support\Period;

abstract class AbstractMetricCalculator implements Contract
{
    protected ?Period $period = null;
    protected array $plans = [];
    protected ?string $currency = null;
    protected ?string $groupBy = null;

    public function __construct(
        protected readonly SubscriptionProvider $provider,
        protected readonly string $baseCurrency
    ) {
    }

    /**
     * Set the period for calculation.
     */
    public function period(\DateTimeInterface|string $period): static
    {
        $this->period = $period instanceof \DateTimeInterface
            ? Period::fromDates($period, now())
            : Period::fromString($period);

        return $this;
    }

    /**
     * Filter by plan(s).
     */
    public function plan(string|array $plans): static
    {
        $this->plans = is_array($plans) ? $plans : [$plans];

        return $this;
    }

    /**
     * Filter by currency.
     */
    public function currency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * Group results by a field.
     */
    public function groupBy(string $field): static
    {
        $this->groupBy = $field;

        return $this;
    }

    /**
     * Get the period instance.
     */
    protected function getPeriod(): Period
    {
        return $this->period ?? Period::fromType(PeriodType::THIS_MONTH);
    }

    /**
     * Get all subscriptions for the current calculation scope.
     */
    protected function getSubscriptions(): Collection
    {
        $subscriptions = $this->provider->getActiveSubscriptions();

        return $this->filterSubscriptions($subscriptions);
    }

    /**
     * Filter subscriptions by plan and currency.
     */
    protected function filterSubscriptions(Collection $subscriptions): Collection
    {
        return $subscriptions->filter(function (array $subscription) {
            // Filter by plan
            if (! empty($this->plans)) {
                $plan = $this->provider->getSubscriptionAttribute($subscription, 'plan');
                if (! in_array($plan, $this->plans, true)) {
                    return false;
                }
            }

            // Filter by currency
            if ($this->currency !== null) {
                $subCurrency = $this->provider->getSubscriptionCurrency($subscription);
                if ($subCurrency !== $this->currency) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Normalize subscription amount to base currency and monthly.
     */
    protected function normalizeAmount(array $subscription): float
    {
        $amount = $this->provider->getSubscriptionAmount($subscription);
        $currency = $this->provider->getSubscriptionCurrency($subscription);
        $interval = $this->provider->getSubscriptionInterval($subscription);

        // Convert to base currency
        $amount = $this->provider->convertToBaseCurrency($amount, $currency);

        // Normalize to monthly
        $intervalType = \PlusInfoLab\CashierSaaSMetrics\Enums\IntervalType::fromString($interval);
        $amount = $amount * $intervalType->getMonthlyMultiplier();

        return $amount;
    }

    /**
     * Get the cache key for this metric calculation.
     */
    public function getCacheKey(): string
    {
        $parts = [
            static::class,
            $this->getPeriod()->start->format('Y-m-d'),
            $this->getPeriod()->end->format('Y-m-d'),
        ];

        if (! empty($this->plans)) {
            $parts[] = implode(',', $this->plans);
        }

        if ($this->currency !== null) {
            $parts[] = $this->currency;
        }

        if ($this->groupBy !== null) {
            $parts[] = 'group:'.$this->groupBy;
        }

        return md5(implode(':', $parts));
    }

    /**
     * Get the cache TTL for this metric.
     */
    public function getCacheTTL(): ?int
    {
        return config('saas-metrics.cache.ttl.short', 300); // 5 minutes default
    }
}
