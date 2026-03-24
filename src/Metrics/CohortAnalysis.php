<?php

namespace PlusInfoLab\CashierSaaSMetrics\Metrics;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PlusInfoLab\CashierSaaSMetrics\Support\MetricResult;

class CohortAnalysis extends AbstractMetricCalculator
{
    protected string $cohortBy = 'created_at';

    protected string $retentionMetric = 'mrr';

    protected int $periods = 6;

    public function by(string $field): self
    {
        $this->cohortBy = $field;

        return $this;
    }

    public function retentionMetric(string $metric): self
    {
        $this->retentionMetric = $metric;

        return $this;
    }

    public function periods(int $periods): self
    {
        $this->periods = $periods;

        return $this;
    }

    public function calculate(): array|MetricResult|float
    {
        $period = $this->getPeriod();
        $subscriptions = $this->getSubscriptions();
        $cohorts = $this->groupCohorts($subscriptions);

        $cohortData = [];
        foreach ($cohorts as $cohortKey => $cohortSubscriptions) {
            $cohortData[$cohortKey] = $this->calculateCohortRetention($cohortSubscriptions, $cohortKey);
        }

        $result = new MetricResult(
            $cohortData,
            'Cohort Analysis',
            $this->retentionMetric === 'mrr' ? $this->baseCurrency : null,
            $period->start->format('M Y')
        );

        return $result->withMetadata([
            'cohort_by' => $this->cohortBy,
            'retention_metric' => $this->retentionMetric,
            'periods' => $this->periods,
            'total_cohorts' => count($cohortData),
        ]);
    }

    protected function groupCohorts(Collection $subscriptions): Collection
    {
        return $subscriptions->groupBy(function (array $subscription) {
            $date = $this->provider->getSubscriptionCreatedAt($subscription);

            return match ($this->cohortBy) {
                'signup_month', 'month' => $date->format('Y-m'),
                'signup_quarter', 'quarter' => $date->format('Y-').ceil($date->month / 3),
                'signup_year', 'year' => $date->format('Y'),
                'signup_week', 'week' => $date->format('Y-W'),
                default => $date->format('Y-m'),
            };
        });
    }

    protected function calculateCohortRetention(Collection $cohort, string $cohortKey): array
    {
        $cohortDate = Carbon::parse($cohortKey.'-01');

        $initialValue = $this->calculateCohortInitialValue($cohort);
        $initialCount = $cohort->count();

        $retentionData = [
            'cohort' => $cohortKey,
            'initial_size' => $initialCount,
            'initial_value' => $initialValue,
            'periods' => [],
        ];

        for ($i = 0; $i < $this->periods; $i++) {
            $periodStart = $cohortDate->copy()->addMonths($i)->startOfMonth();
            $periodEnd = $cohortDate->copy()->addMonths($i)->endOfMonth();

            $retentionValue = $this->calculateRetentionAtPeriod($cohort, $periodStart, $periodEnd);
            $retentionRate = $initialValue > 0 ? $retentionValue / $initialValue : 0;
            $retentionCount = $this->calculateActiveCountAtPeriod($cohort, $periodStart, $periodEnd);

            $retentionData['periods'][] = [
                'period' => $i + 1,
                'date' => $periodStart->format('Y-m'),
                'value' => $retentionValue,
                'count' => $retentionCount,
                'retention_rate' => $retentionRate,
                'retention_percentage' => round($retentionRate * 100, 2),
            ];
        }

        return $retentionData;
    }

    protected function calculateCohortInitialValue(Collection $cohort): float
    {
        return match ($this->retentionMetric) {
            'mrr', 'revenue' => $cohort
                ->map(function (array $sub) {
                    return $this->normalizeAmount($sub);
                })
                ->sum(),
            'customers', 'count' => $cohort->count(),
            default => $cohort->count(),
        };
    }

    protected function calculateRetentionAtPeriod(Collection $cohort, $periodStart, $periodEnd): float
    {
        $subscriptionIds = $cohort
            ->map(function (array $sub) {
                return $this->provider->getSubscriptionAttribute($sub, 'id');
            })
            ->toArray();

        $activeSubscriptions = $this->provider->getActiveSubscriptions()
            ->filter(function (array $sub) use ($subscriptionIds, $periodEnd) {
                $subId = $this->provider->getSubscriptionAttribute($sub, 'id');
                if (! in_array($subId, $subscriptionIds, true)) {
                    return false;
                }

                $cancelledAt = $this->provider->getSubscriptionCancelledAt($sub);

                return $cancelledAt === null || $cancelledAt > $periodEnd;
            });

        return match ($this->retentionMetric) {
            'mrr', 'revenue' => $activeSubscriptions
                ->map(function (array $sub) {
                    return $this->normalizeAmount($sub);
                })
                ->sum(),
            'customers', 'count' => $activeSubscriptions->count(),
            default => $activeSubscriptions->count(),
        };
    }

    protected function calculateActiveCountAtPeriod(Collection $cohort, $periodStart, $periodEnd): int
    {
        $subscriptionIds = $cohort
            ->map(function (array $sub) {
                return $this->provider->getSubscriptionAttribute($sub, 'id');
            })
            ->toArray();

        return $this->provider->getActiveSubscriptions()
            ->filter(function (array $sub) use ($subscriptionIds, $periodEnd) {
                $subId = $this->provider->getSubscriptionAttribute($sub, 'id');
                if (! in_array($subId, $subscriptionIds, true)) {
                    return false;
                }

                $cancelledAt = $this->provider->getSubscriptionCancelledAt($sub);

                return $cancelledAt === null || $cancelledAt > $periodEnd;
            })
            ->count();
    }

    public function getCacheTTL(): ?int
    {
        return config('saas-metrics.cache.ttl.long', 3600);
    }
}
