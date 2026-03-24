<?php

namespace PlusInfoLab\CashierSaaSMetrics\Events;

use Illuminate\Foundation\Events\Dispatchable;
use PlusInfoLab\CashierSaaSMetrics\Support\MetricResult;

class MetricsCalculated
{
    use Dispatchable;

    public function __construct(
        public readonly string $metricType,
        public readonly MetricResult $result,
        public readonly ?string $cacheKey = null
    ) {}
}
