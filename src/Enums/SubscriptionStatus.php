<?php

namespace PlusInfoLab\CashierSaaSMetrics\Enums;

enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case CANCELLED = 'cancelled';
    case PAST_DUE = 'past_due';
    case TRIALING = 'trialing';
    case INCOMPLETE = 'incomplete';
    case INCOMPLETE_EXPIRED = 'incomplete_expired';
    case PAUSED = 'paused';
    case UNPAID = 'unpaid';

    /**
     * Check if the status is considered active for metrics.
     */
    public function isActiveForMetrics(): bool
    {
        return match ($this) {
            self::ACTIVE, self::TRIALING => true,
            default => false,
        };
    }

    /**
     * Check if the subscription is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this === self::CANCELLED;
    }
}
