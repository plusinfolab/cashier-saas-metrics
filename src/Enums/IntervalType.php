<?php

namespace PlusInfoLab\CashierSaaSMetrics\Enums;

enum IntervalType: string
{
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';
    case WEEKLY = 'weekly';
    case QUARTERLY = 'quarterly';
    case CUSTOM = 'custom';

    /**
     * Get the monthly multiplier for MRR calculation.
     */
    public function getMonthlyMultiplier(): float
    {
        return match ($this) {
            self::MONTHLY => 1.0,
            self::YEARLY => 1.0 / 12.0,
            self::WEEKLY => 4.33, // Average weeks per month
            self::QUARTERLY => 1.0 / 3.0,
            self::CUSTOM => 1.0,
        };
    }

    /**
     * Get interval from string.
     */
    public static function fromString(string $interval): self
    {
        return match (strtolower($interval)) {
            'month', 'monthly' => self::MONTHLY,
            'year', 'yearly', 'annual', 'annually' => self::YEARLY,
            'week', 'weekly' => self::WEEKLY,
            'quarter', 'quarterly' => self::QUARTERLY,
            default => self::CUSTOM,
        };
    }
}
