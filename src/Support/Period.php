<?php

namespace PlusInfoLab\CashierSaaSMetrics\Support;

use DateTimeInterface;
use PlusInfoLab\CashierSaaSMetrics\Enums\PeriodType;

readonly class Period
{
    public function __construct(
        public DateTimeInterface $start,
        public DateTimeInterface $end
    ) {}

    /**
     * Create a period from a period type.
     */
    public static function fromType(PeriodType $type, ?DateTimeInterface $customStart = null, ?DateTimeInterface $customEnd = null): self
    {
        [$start, $end] = $type->getDateRange($customStart, $customEnd);

        return new self($start, $end);
    }

    /**
     * Create a period from a string.
     */
    public static function fromString(string $period): self
    {
        $type = PeriodType::fromString($period);

        return self::fromType($type);
    }

    /**
     * Create a period from date range.
     */
    public static function fromDates(DateTimeInterface $start, DateTimeInterface $end): self
    {
        return new self($start, $end);
    }

    /**
     * Get the period duration in days.
     */
    public function durationInDays(): int
    {
        return $this->end->diff($this->start)->days;
    }

    /**
     * Get the period duration in months (approximate).
     */
    public function durationInMonths(): float
    {
        return $this->durationInDays() / 30.44; // Average days per month
    }

    /**
     * Check if a date is within the period.
     */
    public function contains(DateTimeInterface $date): bool
    {
        return $date >= $this->start && $date <= $this->end;
    }

    /**
     * Get previous period of same length.
     */
    public function previous(): self
    {
        $duration = $this->end->diff($this->start);

        return new self(
            $this->start->sub($duration),
            $this->end->sub($duration)
        );
    }
}
