<?php

namespace PlusInfoLab\CashierSaaSMetrics\Contracts;

use PlusInfoLab\CashierSaaSMetrics\Support\MetricResult;

interface MetricCalculator
{
    /**
     * Calculate the metric value.
     *
     * @return float|int|array|MetricResult
     */
    public function calculate(): float|int|array|MetricResult;

    /**
     * Set the period for calculation.
     *
     * @param \DateTimeInterface|string $period
     * @return $this
     */
    public function period(\DateTimeInterface|string $period): static;

    /**
     * Filter by plan(s).
     *
     * @param string|array $plans
     * @return $this
     */
    public function plan(string|array $plans): static;

    /**
     * Filter by currency.
     *
     * @param string $currency
     * @return $this
     */
    public function currency(string $currency): static;

    /**
     * Group results by a field.
     *
     * @param string $field
     * @return $this
     */
    public function groupBy(string $field): static;

    /**
     * Get the cache key for this metric calculation.
     *
     * @return string
     */
    public function getCacheKey(): string;

    /**
     * Get the cache TTL for this metric.
     *
     * @return int|null
     */
    public function getCacheTTL(): ?int;
}
