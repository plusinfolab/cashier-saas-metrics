<?php

namespace PlusInfoLab\CashierSaaSMetrics\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SubscriptionCancelled
{
    use Dispatchable;

    public function __construct(
        public readonly string|int $subscriptionId,
        public readonly ?string $customerId = null,
        public readonly ?array $subscriptionData = null
    ) {
    }
}
