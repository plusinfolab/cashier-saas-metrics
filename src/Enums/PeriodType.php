<?php

namespace PlusInfoLab\CashierSaaSMetrics\Enums;

enum PeriodType: string
{
    case TODAY = 'today';
    case YESTERDAY = 'yesterday';
    case THIS_WEEK = 'this_week';
    case LAST_WEEK = 'last_week';
    case THIS_MONTH = 'this_month';
    case LAST_MONTH = 'last_month';
    case THIS_QUARTER = 'this_quarter';
    case LAST_QUARTER = 'last_quarter';
    case THIS_YEAR = 'this_year';
    case LAST_YEAR = 'last_year';
    case ALL_TIME = 'all_time';
    case CUSTOM = 'custom';

    /**
     * Get the date range for the period.
     *
     * @return array{\DateTimeInterface, \DateTimeInterface}
     */
    public function getDateRange(?\DateTimeInterface $customStart = null, ?\DateTimeInterface $customEnd = null): array
    {
        return match ($this) {
            self::TODAY => [now()->startOfDay(), now()->endOfDay()],
            self::YESTERDAY => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            self::THIS_WEEK => [now()->startOfWeek(), now()->endOfWeek()],
            self::LAST_WEEK => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            self::THIS_MONTH => [now()->startOfMonth(), now()->endOfMonth()],
            self::LAST_MONTH => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            self::THIS_QUARTER => [now()->startOfQuarter(), now()->endOfQuarter()],
            self::LAST_QUARTER => [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()],
            self::THIS_YEAR => [now()->startOfYear(), now()->endOfYear()],
            self::LAST_YEAR => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            self::ALL_TIME => [now()->subYears(100), now()],
            self::CUSTOM => [$customStart ?? now(), $customEnd ?? now()],
        };
    }

    /**
     * Get period from string.
     */
    public static function fromString(string $period): self
    {
        return self::tryFrom($period) ?? self::CUSTOM;
    }
}
