<?php

namespace PlusInfoLab\CashierSaaSMetrics\Metrics;

use Illuminate\Support\Collection;
use PlusInfoLab\CashierSaaSMetrics\Support\MetricResult;

class MRR extends AbstractMetricCalculator
{
    protected bool $includeNew = true;
    protected bool $includeChurned = false;
    protected bool $includeTrials = false;

    public function includeNew(bool $include = true): self
    {
        $this->includeNew = $include;
        return $this;
    }

    public function includeChurned(bool $include = true): self
    {
        $this->includeChurned = $include;
        return $this;
    }

    public function includeTrials(bool $include = true): self
    {
        $this->includeTrials = $include;
        return $this;
    }

    public function calculate(): float|MetricResult|array
    {
        $subscriptions = $this->getSubscriptions();

        if (! $this->includeTrials) {
            $subscriptions = $subscriptions->reject(function (array $sub) {
                return $this->provider->isOnTrial($sub);
            });
        }

        if ($this->groupBy !== null) {
            return $this->calculateGrouped($subscriptions);
        }

        $mrr = $subscriptions
            ->reject(function (array $sub) {
                return ! $this->shouldIncludeSubscription($sub);
            })
            ->map(function (array $sub) {
                return $this->normalizeAmount($sub);
            })
            ->sum();

        return $this->includeChurned
            ? $this->calculateWithChurn($mrr, $subscriptions)
            : new MetricResult(
                $mrr,
                'MRR',
                $this->baseCurrency,
                $this->getPeriod()->start->format('M Y')
            );
    }

    protected function calculateWithChurn(float $currentMrr, Collection $subscriptions): MetricResult
    {
        $period = $this->getPeriod();
        $previousPeriod = $period->previous();

        $churnedSubscriptions = $this->provider->getCancelledSubscriptions(
            $previousPeriod->start,
            $period->end
        );

        $churnedMrr = $this->filterSubscriptions($churnedSubscriptions)
            ->map(function (array $sub) {
                return $this->normalizeAmount($sub);
            })
            ->sum();

        $netMrr = $currentMrr - $churnedMrr;

        $result = new MetricResult(
            $netMrr,
            'Net MRR',
            $this->baseCurrency,
            $period->start->format('M Y')
        );

        return $result->withMetadata([
            'gross_mrr' => $currentMrr,
            'churned_mrr' => $churnedMrr,
            'subscription_count' => $subscriptions->count(),
        ]);
    }

    protected function calculateGrouped(Collection $subscriptions): array
    {
        return $subscriptions
            ->reject(function (array $sub) {
                return ! $this->shouldIncludeSubscription($sub);
            })
            ->groupBy(function (array $sub) {
                return $this->provider->getSubscriptionAttribute($sub, $this->groupBy);
            })
            ->map(function (Collection $group) {
                $mrr = $group
                    ->map(function (array $sub) {
                        return $this->normalizeAmount($sub);
                    })
                    ->sum();

                return [
                    'value' => $mrr,
                    'currency' => $this->baseCurrency,
                    'count' => $group->count(),
                ];
            })
            ->toArray();
    }

    protected function shouldIncludeSubscription(array $subscription): bool
    {
        $status = $this->provider->getSubscriptionStatus($subscription);
        $createdAt = $this->provider->getSubscriptionCreatedAt($subscription);

        if ($this->includeNew && $this->getPeriod()->contains($createdAt)) {
            return true;
        }

        return $this->provider->isActive($subscription);
    }
}
