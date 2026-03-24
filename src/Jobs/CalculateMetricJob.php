<?php

namespace PlusInfoLab\CashierSaaSMetrics\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PlusInfoLab\CashierSaaSMetrics\Facades\Metrics;

class CalculateMetricJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Indicate if the job should be marked as failed on timeout.
     */
    public bool $failOnTimeout = true;

    public function __construct(
        public readonly string $metricType,
        public readonly ?array $filters = null
    ) {
        $this->onQueue(config('saas-metrics.queue', 'default'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $facade = Metrics::getFacadeRoot();

        if (! $facade) {
            return;
        }

        try {
            match ($this->metricType) {
                'mrr' => $facade->mrr()->calculate(),
                'churn_rate', 'churn' => $facade->churnRate()->calculate(),
                'ltv', 'lifetime_value' => $facade->lifetimeValue()->calculate(),
                'arpu' => $facade->arpu()->calculate(),
                'cohort', 'cohorts' => $facade->cohorts()->calculate(),
                default => null,
            };
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * Get the tags the job should be monitored with.
     */
    public function tags(): array
    {
        return [
            'saas-metrics',
            'calculate:'.$this->metricType,
        ];
    }
}
