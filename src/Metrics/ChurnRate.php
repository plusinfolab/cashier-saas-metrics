<?php

namespace PlusInfoLab\CashierSaaSMetrics\Metrics;

use Illuminate\Support\Collection;
use PlusInfoLab\CashierSaaSMetrics\Support\MetricResult;

class ChurnRate extends AbstractMetricCalculator
{
    protected bool $calculateGrossChurn = false;

    public function gross(bool $gross = true): self
    {
        $this->calculateGrossChurn = $gross;
        return $this;
    }

    public function calculate(): float|MetricResult
    {
        $period = $this->getPeriod();
        $previousPeriod = $period->previous();

        $startingMrr = $this->calculateStartingMRR($previousPeriod);

        if ($startingMrr == 0) {
            return new MetricResult(0, 'Churn Rate', null, $period->start->format('M Y'));
        }

        $cancelledSubscriptions = $this->provider->getCancelledSubscriptions(
            $period->start,
            $period->end
        );

        $filteredCancellations = $this->filterSubscriptions($cancelledSubscriptions);

        $churnedMrr = $filteredCancellations
            ->map(function (array $sub) {
                return $this->normalizeAmount($sub);
            })
            ->sum();

        $newSubscriptions = $this->provider->getNewSubscriptions(
            $period->start,
            $period->end
        );

        $filteredNew = $this->filterSubscriptions($newSubscriptions);
        $newMrr = $filteredNew
            ->map(function (array $sub) {
                return $this->normalizeAmount($sub);
            })
            ->sum();

        if ($this->calculateGrossChurn) {
            $churnRate = $churnedMrr / $startingMrr;
        } else {
            $churnRate = ($churnedMrr - $newMrr) / $startingMrr;
        }

        $result = new MetricResult(
            max(0, min(1, $churnRate)),
            'Churn Rate',
            null,
            $period->start->format('M Y')
        );

        return $result->withMetadata([
            'starting_mrr' => $startingMrr,
            'churned_mrr' => $churnedMrr,
            'new_mrr' => $newMrr,
            'churned_subscriptions' => $filteredCancellations->count(),
            'new_subscriptions' => $filteredNew->count(),
            'gross_churn' => $this->calculateGrossChurn,
        ]);
    }

    protected function calculateStartingMRR($previousPeriod): float
    {
        $subscriptions = $this->provider->getActiveSubscriptions();

        return $this->filterSubscriptions($subscriptions)
            ->filter(function (array $subscription) use ($previousPeriod) {
                $createdAt = $this->provider->getSubscriptionCreatedAt($subscription);
                return $createdAt < $previousPeriod->end;
            })
            ->reject(function (array $sub) {
                return $this->provider->isOnTrial($sub);
            })
            ->map(function (array $sub) {
                return $this->normalizeAmount($sub);
            })
            ->sum();
    }

    public function getCacheTTL(): ?int
    {
        return config('saas-metrics.cache.ttl.short', 300);
    }
}
