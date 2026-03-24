<?php

namespace PlusInfoLab\CashierSaaSMetrics\Metrics;

use Illuminate\Support\Collection;
use PlusInfoLab\CashierSaaSMetrics\Support\MetricResult;

class ARPU extends AbstractMetricCalculator
{
    protected bool $activeOnly = true;

    public function activeOnly(bool $activeOnly = true): self
    {
        $this->activeOnly = $activeOnly;

        return $this;
    }

    public function calculate(): float|MetricResult|array
    {
        if ($this->groupBy !== null) {
            return $this->calculateGrouped();
        }

        $period = $this->getPeriod();
        $subscriptions = $this->getSubscriptions();

        if ($this->activeOnly) {
            $subscriptions = $subscriptions->filter(function (array $sub) {
                return $this->provider->isActive($sub) &&
                       ! $this->provider->isOnTrial($sub);
            });
        }

        $customerIds = $subscriptions
            ->map(function (array $sub) {
                return $this->provider->getSubscriptionAttribute($sub, 'customer_id');
            })
            ->unique()
            ->values();

        $payments = $this->provider->getPayments($period->start, $period->end);

        $totalRevenue = 0;
        $payingCustomerCount = 0;

        foreach ($customerIds as $customerId) {
            $subscriptionIds = $subscriptions
                ->map(function (array $sub) {
                    return $this->provider->getSubscriptionAttribute($sub, 'id');
                })
                ->toArray();

            $customerPayments = $payments->filter(function ($payment) use ($customerId, $subscriptionIds) {
                $paymentCustomerId = $payment['customer_id'] ?? null;
                $subscriptionId = $payment['subscription_id'] ?? null;

                $matchesCustomer = $paymentCustomerId == $customerId;
                $matchesSubscription = in_array($subscriptionId, $subscriptionIds, true);

                return $matchesCustomer || $matchesSubscription;
            });

            if ($customerPayments->isNotEmpty()) {
                foreach ($customerPayments as $payment) {
                    $amount = $payment['amount'] ?? 0;
                    $currency = $payment['currency'] ?? $this->baseCurrency;
                    $totalRevenue += $this->provider->convertToBaseCurrency($amount, $currency);
                }
                $payingCustomerCount++;
            }
        }

        $arpu = $payingCustomerCount > 0 ? $totalRevenue / $payingCustomerCount : 0;

        $result = new MetricResult(
            $arpu,
            'ARPU',
            $this->baseCurrency,
            $period->start->format('M Y')
        );

        return $result->withMetadata([
            'total_revenue' => $totalRevenue,
            'paying_customers' => $payingCustomerCount,
            'total_customers' => $customerIds->count(),
            'active_only' => $this->activeOnly,
        ]);
    }

    protected function calculateGrouped(): array
    {
        $period = $this->getPeriod();
        $subscriptions = $this->getSubscriptions();
        $payments = $this->provider->getPayments($period->start, $period->end);

        return $subscriptions
            ->groupBy(function (array $sub) {
                return $this->provider->getSubscriptionAttribute($sub, $this->groupBy);
            })
            ->map(function (Collection $group, $key) use ($payments) {
                if ($this->activeOnly) {
                    $group = $group->filter(function (array $sub) {
                        return $this->provider->isActive($sub) &&
                               ! $this->provider->isOnTrial($sub);
                    });
                }

                $subscriptionIds = $group
                    ->map(function (array $sub) {
                        return $this->provider->getSubscriptionAttribute($sub, 'id');
                    })
                    ->toArray();

                $groupPayments = $payments->filter(function ($payment) use ($subscriptionIds) {
                    return in_array($payment['subscription_id'] ?? null, $subscriptionIds, true);
                });

                $totalRevenue = $groupPayments->sum(function ($payment) {
                    $amount = $payment['amount'] ?? 0;
                    $currency = $payment['currency'] ?? $this->baseCurrency;

                    return $this->provider->convertToBaseCurrency($amount, $currency);
                });

                $uniqueCustomers = $group
                    ->map(function (array $sub) {
                        return $this->provider->getSubscriptionAttribute($sub, 'customer_id');
                    })
                    ->unique()
                    ->count();

                $arpu = $uniqueCustomers > 0 ? $totalRevenue / $uniqueCustomers : 0;

                return [
                    'value' => $arpu,
                    'currency' => $this->baseCurrency,
                    'total_revenue' => $totalRevenue,
                    'customer_count' => $uniqueCustomers,
                    'subscription_count' => $group->count(),
                ];
            })
            ->toArray();
    }

    public function getCacheTTL(): ?int
    {
        return config('saas-metrics.cache.ttl.medium', 600);
    }
}
