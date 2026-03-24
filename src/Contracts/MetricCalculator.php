<?php

namespace PlusInfoLab\CashierSaaSMetrics\Contracts;

use PlusInfoLab\CashierSaaSMetrics\Support\MetricResult;

interface MetricCalculator
{
    /**
     * Calculate the metric value.
     */
    public function calculate(): float|int|array|MetricResult;

    /**
     * Set the period for calculation.
     *
     * @return $this
     */
    public function period(\DateTimeInterface|string $period): static;

    /**
     * Filter by plan(s).
     *
     * @return $this
     */
    public function plan(string|array $plans): static;

    /**
     * Filter by currency.
     *
     * @return $this
     */
    public function currency(string $currency): static;

    /**
     * Group results by a field.
     *
     * @return $this
     */
    public function groupBy(string $field): static;

    /**
     * Get the cache key for this metric calculation.
     */
    public function getCacheKey(): string;

    /**
     * Get the cache TTL for this metric.
     */
    public function getCacheTTL(): ?int;
}
