<?php

namespace PlusInfoLab\CashierSaaSMetrics\Support;

use Illuminate\Contracts\Support\Arrayable;

class MetricResult implements Arrayable
{
    protected array $metadata = [];

    public function __construct(
        public readonly float|int|string $value,
        public readonly string $name,
        public readonly ?string $currency = null,
        public readonly ?string $period = null
    ) {}

    /**
     * Create a new metric result.
     */
    public static function make(
        float|int|string $value,
        string $name,
        ?string $currency = null,
        ?string $period = null
    ): self {
        return new self($value, $name, $currency, $period);
    }

    /**
     * Set metadata for the result.
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Get the value formatted as currency.
     */
    public function formattedAsCurrency(?string $locale = null): string
    {
        if ($this->currency === null) {
            return (string) $this->value;
        }

        return number_format($this->value, 2).' '.$this->currency;
    }

    /**
     * Get the value formatted as percentage.
     */
    public function formattedAsPercentage(int $decimals = 2): string
    {
        return number_format($this->value * 100, $decimals).'%';
    }

    /**
     * Get the value formatted as number.
     */
    public function formattedAsNumber(int $decimals = 0): string
    {
        return number_format($this->value, $decimals);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
            'currency' => $this->currency,
            'period' => $this->period,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Convert to JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * Get the raw value.
     */
    public function value(): float|int|string
    {
        return $this->value;
    }

    /**
     * Get metadata.
     */
    public function metadata(): array
    {
        return $this->metadata;
    }
}
