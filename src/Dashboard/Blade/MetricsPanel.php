<?php

namespace PlusInfoLab\CashierSaaSMetrics\Dashboard\Blade;

use Illuminate\View\Component;
use PlusInfoLab\CashierSaaSMetrics\Facades\Metrics;

class MetricsPanel extends Component
{
    public array $metrics;

    public string $period;

    public function __construct(
        public readonly ?string $title = null,
        ?string $period = null
    ) {
        $this->period = $period ?? 'this_month';
        $this->metrics = $this->loadMetrics();
    }

    /**
     * Load metrics for the dashboard.
     */
    protected function loadMetrics(): array
    {
        try {
            $dashboard = Metrics::dashboard($this->period);

            return [
                'mrr' => [
                    'title' => 'Monthly Recurring Revenue',
                    'value' => $dashboard['mrr'],
                    'icon' => 'heroicon-o-currency-dollar',
                    'color' => 'green',
                    'format' => 'currency',
                ],
                'churn_rate' => [
                    'title' => 'Churn Rate',
                    'value' => $dashboard['churn_rate'],
                    'icon' => 'heroicon-o-chart-bar',
                    'color' => 'red',
                    'format' => 'percentage',
                ],
                'ltv' => [
                    'title' => 'Lifetime Value',
                    'value' => $dashboard['ltv'],
                    'icon' => 'heroicon-o-user-group',
                    'color' => 'blue',
                    'format' => 'currency',
                ],
                'arpu' => [
                    'title' => 'Avg. Revenue Per User',
                    'value' => $dashboard['arpu'],
                    'icon' => 'heroicon-o-users',
                    'color' => 'purple',
                    'format' => 'currency',
                ],
            ];
        } catch (\Exception $e) {
            report($e);

            return [];
        }
    }

    /**
     * Format a metric value for display.
     */
    public function formatMetric(array $metric): string
    {
        $value = $metric['value'];

        if ($value instanceof \PlusInfoLab\CashierSaaSMetrics\Support\MetricResult) {
            return match ($metric['format'] ?? null) {
                'currency' => $value->formattedAsCurrency(),
                'percentage' => $value->formattedAsPercentage(),
                default => (string) $value->value(),
            };
        }

        return match ($metric['format'] ?? null) {
            'currency' => number_format((float) $value, 2),
            'percentage' => number_format((float) $value * 100, 2).'%',
            default => number_format((float) $value),
        };
    }

    public function render()
    {
        return view('saas-metrics::components.metrics-panel');
    }
}
