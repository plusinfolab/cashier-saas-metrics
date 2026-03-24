<?php

namespace PlusInfoLab\CashierSaaSMetrics\Metrics;

use Illuminate\Support\Collection;
use PlusInfoLab\CashierSaaSMetrics\Support\MetricResult;

class LifetimeValue extends AbstractMetricCalculator
{
    protected ?float $arpu = null;

    protected ?float $churnRate = null;

    public function withArpu(?float $arpu): self
    {
        $this->arpu = $arpu;

        return $this;
    }

    public function withChurnRate(?float $churnRate): self
    {
        $this->churnRate = $churnRate;

        return $this;
    }

    public function calculate(): float|MetricResult|array
    {
        if ($this->groupBy !== null) {
            return $this->calculateGrouped();
        }

        $arpu = $this->arpu ?? $this->calculateARPU();
        $churnRate = $this->churnRate ?? $this->calculateChurnRate();

        if ($churnRate == 0) {
            $ltv = $arpu * 60;
        } else {
            $ltv = $arpu / $churnRate;
        }

        $result = new MetricResult(
            $ltv,
            'LTV',
            $this->baseCurrency,
            $this->getPeriod()->start->format('M Y')
        );

        return $result->withMetadata([
            'arpu' => $arpu,
            'churn_rate' => $churnRate,
            'calculation_method' => $churnRate == 0 ? 'default_months' : 'arpu_churn',
        ]);
    }

    protected function calculateGrouped(): array
    {
        $subscriptions = $this->getSubscriptions();
        $payments = $this->getAllPayments();

        return $subscriptions
            ->groupBy(function (array $sub) {
                return $this->provider->getSubscriptionAttribute($sub, $this->groupBy);
            })
            ->map(function (Collection $group) use ($payments) {
                $groupIds = $group->map(function (array $sub) {
                    return $this->provider->getSubscriptionAttribute($sub, 'id');
                })->toArray();

                $groupPayments = $payments->filter(function ($payment) use ($groupIds) {
                    return in_array($payment['subscription_id'] ?? null, $groupIds, true);
                });

                $arpu = $this->calculateGroupARPU($group, $groupPayments);
                $churnRate = $this->calculateGroupChurnRate($group);
                $ltv = $churnRate > 0 ? $arpu / $churnRate : $arpu * 60;

                return [
                    'value' => $ltv,
                    'currency' => $this->baseCurrency,
                    'arpu' => $arpu,
                    'churn_rate' => $churnRate,
                    'count' => $group->count(),
                ];
            })
            ->toArray();
    }

    protected function calculateARPU(): float
    {
        $period = $this->getPeriod();
        $subscriptions = $this->getSubscriptions();
        $payments = $this->provider->getPayments($period->start, $period->end);

        $totalRevenue = 0;
        $customerCount = 0;

        foreach ($subscriptions as $subscription) {
            $customerId = $this->provider->getSubscriptionAttribute($subscription, 'customer_id');
            $subscriptionId = $this->provider->getSubscriptionAttribute($subscription, 'id');

            $subscriptionPayments = $payments->filter(function ($payment) use ($customerId, $subscriptionId) {
                $paymentCustomerId = $payment['customer_id'] ?? null;
                $paymentSubscriptionId = $payment['subscription_id'] ?? null;

                return $paymentCustomerId == $customerId || $paymentSubscriptionId == $subscriptionId;
            });

            foreach ($subscriptionPayments as $payment) {
                $amount = $payment['amount'] ?? 0;
                $currency = $payment['currency'] ?? $this->baseCurrency;
                $totalRevenue += $this->provider->convertToBaseCurrency($amount, $currency);
            }

            $customerCount++;
        }

        return $customerCount > 0 ? $totalRevenue / $customerCount : 0;
    }

    protected function calculateGroupARPU(Collection $group, Collection $payments): float
    {
        $totalRevenue = $payments->sum(function ($payment) {
            $amount = $payment['amount'] ?? 0;
            $currency = $payment['currency'] ?? $this->baseCurrency;

            return $this->provider->convertToBaseCurrency($amount, $currency);
        });

        return $group->count() > 0 ? $totalRevenue / $group->count() : 0;
    }

    protected function calculateChurnRate(): float
    {
        $period = $this->getPeriod();
        $previousPeriod = $period->previous();

        $subscriptions = $this->getSubscriptions();
        $startingCount = $subscriptions
            ->filter(function (array $subscription) use ($previousPeriod) {
                $createdAt = $this->provider->getSubscriptionCreatedAt($subscription);

                return $createdAt < $previousPeriod->end;
            })
            ->count();

        if ($startingCount == 0) {
            return 0;
        }

        $cancelledSubscriptions = $this->provider->getCancelledSubscriptions(
            $period->start,
            $period->end
        );

        $churnedCount = $this->filterSubscriptions($cancelledSubscriptions)->count();

        return $churnedCount / $startingCount;
    }

    protected function calculateGroupChurnRate(Collection $group): float
    {
        $period = $this->getPeriod();
        $previousPeriod = $period->previous();

        $startingCount = $group
            ->filter(function (array $subscription) use ($previousPeriod) {
                $createdAt = $this->provider->getSubscriptionCreatedAt($subscription);

                return $createdAt < $previousPeriod->end;
            })
            ->count();

        if ($startingCount == 0) {
            return 0;
        }

        $cancelledSubscriptions = $this->provider->getCancelledSubscriptions(
            $period->start,
            $period->end
        );

        $groupIds = $group->map(function (array $sub) {
            return $this->provider->getSubscriptionAttribute($sub, 'id');
        })->toArray();

        $churnedCount = $cancelledSubscriptions
            ->filter(function (array $sub) use ($groupIds) {
                return in_array($this->provider->getSubscriptionAttribute($sub, 'id'), $groupIds, true);
            })
            ->count();

        return $churnedCount / $startingCount;
    }

    protected function getAllPayments(): Collection
    {
        $period = $this->getPeriod();

        return $this->provider->getPayments($period->start, $period->end);
    }

    public function getCacheTTL(): ?int
    {
        return config('saas-metrics.cache.ttl.long', 3600);
    }
}
