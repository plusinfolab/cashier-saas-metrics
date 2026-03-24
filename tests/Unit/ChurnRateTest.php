<?php

use Illuminate\Support\Collection;
use PlusInfoLab\CashierSaaSMetrics\Contracts\SubscriptionProvider;
use PlusInfoLab\CashierSaaSMetrics\Facades\Metrics;
use PlusInfoLab\CashierSaaSMetrics\Providers\SubscriptionProviderFactory;

beforeEach(function () {
    $this->mockProvider = Mockery::mock(SubscriptionProvider::class);

    // Set up default expectations for common methods
    $this->mockProvider
        ->shouldReceive('getSubscriptionAmount')
        ->byDefault()
        ->andReturnUsing(fn ($sub) => $sub['amount'] ?? 0);

    $this->mockProvider
        ->shouldReceive('getSubscriptionCurrency')
        ->byDefault()
        ->andReturnUsing(fn ($sub) => $sub['currency'] ?? 'USD');

    $this->mockProvider
        ->shouldReceive('getSubscriptionInterval')
        ->byDefault()
        ->andReturnUsing(fn ($sub) => $sub['interval'] ?? 'monthly');

    $this->mockProvider
        ->shouldReceive('convertToBaseCurrency')
        ->byDefault()
        ->andReturnUsing(fn ($amount, $currency) => $amount);

    $this->mockProvider
        ->shouldReceive('isActive')
        ->byDefault()
        ->andReturnUsing(fn ($sub) => ($sub['status'] ?? '') === 'active');

    $this->mockProvider
        ->shouldReceive('isOnTrial')
        ->byDefault()
        ->andReturnUsing(fn ($sub) => ($sub['trial_ends_at'] ?? null) !== null && ($sub['trial_ends_at'] > now()));

    $this->mockProvider
        ->shouldReceive('getSubscriptionAttribute')
        ->byDefault()
        ->andReturnUsing(fn ($sub, $key) => $sub[$key] ?? null);

    $this->mockProvider
        ->shouldReceive('getSubscriptionStatus')
        ->byDefault()
        ->andReturnUsing(fn ($sub) => $sub['status'] ?? 'unknown');

    $this->mockProvider
        ->shouldReceive('getSubscriptionCreatedAt')
        ->byDefault()
        ->andReturnUsing(fn ($sub) => $sub['created_at'] instanceof DateTimeInterface ? $sub['created_at'] : now());

    $this->mockProvider
        ->shouldReceive('getSubscriptionCancelledAt')
        ->byDefault()
        ->andReturnUsing(fn ($sub) => $sub['cancelled_at'] ?? null);

    // Mock the provider factory to return our mocked provider
    $mockFactory = Mockery::mock(SubscriptionProviderFactory::class);
    $mockFactory->shouldReceive('driver')->andReturn($this->mockProvider);

    $this->app->instance(SubscriptionProviderFactory::class, $mockFactory);
    $this->app->instance('saas-metrics.provider', $mockFactory);
});

it('calculates net churn rate', function () {
    $activeSubscriptions = Collection::make([
        [
            'id' => 'sub_1',
            'status' => 'active',
            'amount' => 100,
            'currency' => 'USD',
            'interval' => 'monthly',
            'created_at' => now()->subMonths(2),
            'customer_id' => 'cust_1',
        ],
        [
            'id' => 'sub_2',
            'status' => 'active',
            'amount' => 50,
            'currency' => 'USD',
            'interval' => 'monthly',
            'created_at' => now()->subMonths(2),
            'customer_id' => 'cust_2',
        ],
    ]);

    $cancelledSubscriptions = Collection::make([
        [
            'id' => 'sub_3',
            'status' => 'cancelled',
            'amount' => 75,
            'currency' => 'USD',
            'interval' => 'monthly',
            'created_at' => now()->subMonths(2),
            'cancelled_at' => now()->subWeek(),
            'customer_id' => 'cust_3',
        ],
    ]);

    $newSubscriptions = Collection::make([
        [
            'id' => 'sub_4',
            'status' => 'active',
            'amount' => 120,
            'currency' => 'USD',
            'interval' => 'monthly',
            'created_at' => now()->subWeek(),
            'customer_id' => 'cust_4',
        ],
    ]);

    $this->mockProvider
        ->shouldReceive('getActiveSubscriptions')
        ->once()
        ->andReturn($activeSubscriptions);

    $this->mockProvider
        ->shouldReceive('getCancelledSubscriptions')
        ->once()
        ->andReturn($cancelledSubscriptions);

    $this->mockProvider
        ->shouldReceive('getNewSubscriptions')
        ->once()
        ->andReturn($newSubscriptions);

    $churnRate = Metrics::churnRate();
    $result = $churnRate->calculate();

    // Starting MRR = 150, Churned = 75, New = 120
    // Net Churn = (75 - 120) / 150 = -0.3 (negative = growth)
    expect($result->value)->toBeGreaterThanOrEqual(0);
    expect($result->value)->toBeLessThanOrEqual(1);
});

it('calculates gross churn rate', function () {
    $activeSubscriptions = Collection::make([
        [
            'id' => 'sub_1',
            'status' => 'active',
            'amount' => 100,
            'currency' => 'USD',
            'interval' => 'monthly',
            'created_at' => now()->subMonths(2),
            'customer_id' => 'cust_1',
        ],
    ]);

    $cancelledSubscriptions = Collection::make([
        [
            'id' => 'sub_2',
            'status' => 'cancelled',
            'amount' => 50,
            'currency' => 'USD',
            'interval' => 'monthly',
            'created_at' => now()->subMonths(2),
            'cancelled_at' => now()->subWeek(),
            'customer_id' => 'cust_2',
        ],
    ]);

    $this->mockProvider
        ->shouldReceive('getActiveSubscriptions')
        ->once()
        ->andReturn($activeSubscriptions);

    $this->mockProvider
        ->shouldReceive('getCancelledSubscriptions')
        ->once()
        ->andReturn($cancelledSubscriptions);

    $this->mockProvider
        ->shouldReceive('getNewSubscriptions')
        ->once()
        ->andReturn(Collection::make());

    $churnRate = Metrics::churnRate()->gross();
    $result = $churnRate->calculate();

    expect($result->value)->toBeFloat();
    expect($result->metadata()['gross_churn'])->toBeTrue();
});
