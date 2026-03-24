<?php

namespace PlusInfoLab\CashierSaaSMetrics\Dashboard\Blade;

use Illuminate\View\Component;
use PlusInfoLab\CashierSaaSMetrics\Support\MetricResult;

class MetricCard extends Component
{
    public function __construct(
        public readonly string $title,
        public readonly MetricResult|string|int|float|null $value,
        public readonly ?string $icon = null,
        public readonly ?string $color = null,
        public readonly ?string $trend = null,
        public readonly ?string $period = null
    ) {}

    /**
     * Get the value for display.
     */
    public function displayValue(): string
    {
        if ($this->value instanceof MetricResult) {
            return $this->formatMetricResult($this->value);
        }

        return $this->formatValue($this->value);
    }

    /**
     * Format a metric result for display.
     */
    protected function formatMetricResult(MetricResult $result): string
    {
        $value = $result->value();

        return match (true) {
            str_contains(strtolower($result->name), 'rate') => $result->formattedAsPercentage(),
            $result->currency !== null => $result->formattedAsCurrency(),
            default => number_format((float) $value),
        };
    }

    /**
     * Format a value for display.
     */
    protected function formatValue($value): string
    {
        if (is_null($value)) {
            return 'N/A';
        }

        if (is_numeric($value)) {
            return number_format((float) $value);
        }

        return (string) $value;
    }

    /**
     * Get the color classes for the card.
     */
    public function colorClasses(): string
    {
        return match ($this->color) {
            'green', 'success' => 'bg-green-500 bg-opacity-10 text-green-600',
            'red', 'danger' => 'bg-red-500 bg-opacity-10 text-red-600',
            'yellow', 'warning' => 'bg-yellow-500 bg-opacity-10 text-yellow-600',
            'blue', 'info' => 'bg-blue-500 bg-opacity-10 text-blue-600',
            'purple', 'primary' => 'bg-purple-500 bg-opacity-10 text-purple-600',
            default => 'bg-gray-500 bg-opacity-10 text-gray-600',
        };
    }

    /**
     * Get the trend icon.
     */
    public function trendIcon(): ?string
    {
        return match ($this->trend) {
            'up' => 'heroicon-o-arrow-trending-up',
            'down' => 'heroicon-o-arrow-trending-down',
            default => null,
        };
    }

    /**
     * Get the trend color classes.
     */
    public function trendColorClasses(): string
    {
        return match ($this->trend) {
            'up' => 'text-green-600',
            'down' => 'text-red-600',
            default => 'text-gray-600',
        };
    }

    public function render()
    {
        return view('saas-metrics::components.metric-card');
    }
}
