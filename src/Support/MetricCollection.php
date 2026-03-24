<?php

namespace PlusInfoLab\CashierSaaSMetrics\Support;

use Illuminate\Support\Collection;
use PlusInfoLab\CashierSaaSMetrics\Contracts\MetricCalculator;

class MetricCollection extends Collection
{
    /**
     * Create a new metric collection.
     */
    public static function fromCalculators(array $calculators): self
    {
        $results = [];

        foreach ($calculators as $calculator) {
            if ($calculator instanceof MetricCalculator) {
                $results[$calculator::class] = $calculator->calculate();
            }
        }

        return new self($results);
    }

    /**
     * Get all metric names.
     */
    public function names(): self
    {
        return $this->keys();
    }

    /**
     * Get all metric values.
     */
    public function values(): self
    {
        return new self($this->items);
    }

    /**
     * Filter metrics by type.
     */
    public function byType(string $type): self
    {
        return $this->filter(fn ($value, string $key) => str_contains($key, $type));
    }

    /**
     * Convert to array with formatted values.
     */
    public function toFormattedArray(): array
    {
        return $this->map(function ($value, $key) {
            if ($value instanceof MetricResult) {
                return $value->toArray();
            }

            return [
                'name' => $key,
                'value' => $value,
            ];
        })->toArray();
    }
}
