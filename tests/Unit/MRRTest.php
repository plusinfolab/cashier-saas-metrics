<?php

use PlusInfoLab\CashierSaaSMetrics\Contracts\SubscriptionProvider;
use PlusInfoLab\CashierSaaSMetrics\Facades\Metrics;
use PlusInfoLab\CashierSaaSMetrics\Metrics\MRR;
use PlusInfoLab\CashierSaaSMetrics\Providers\SubscriptionProviderFactory;
use PlusInfoLab\CashierSaaSMetrics\Tests\TestCase;
use Illuminate\Support\Collection;

beforeEach(function () {
    // Mock the subscription provider
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
        ->andReturnUsing(fn ($sub) => $sub['created_at'] instanceof \DateTimeInterface ? $sub['created_at'] : now());

    // Mock the provider factory to return our mocked provider
    $mockFactory = Mockery::mock(SubscriptionProviderFactory::class);
    $mockFactory->shouldReceive('driver')->andReturn($this->mockProvider);

    $this->app->instance(SubscriptionProviderFactory::class, $mockFactory);
    $this->app->instance('saas-metrics.provider', $mockFactory);
});

it('calculates MRR for active subscriptions', function () {
    $subscriptions = Collection::make([
        [
            'id' => 'sub_1',
            'status' => 'active',
            'amount' => 100,
            'currency' => 'USD',
            'interval' => 'monthly',
            'plan' => 'pro',
            'created_at' => now()->subMonth(),
            'customer_id' => 'cust_1',
        ],
        [
            'id' => 'sub_2',
            'status' => 'active',
            'amount' => 50,
            'currency' => 'USD',
            'interval' => 'monthly',
            'plan' => 'basic',
            'created_at' => now()->subMonth(),
            'customer_id' => 'cust_2',
        ],
    ]);

    $this->mockProvider
        ->shouldReceive('getActiveSubscriptions')
        ->once()
        ->andReturn($subscriptions);

    $mrr = Metrics::mrr();

    expect($mrr)->toBeInstanceOf(MRR::class);

    $result = $mrr->calculate();

    expect($result)->toBeInstanceOf(\PlusInfoLab\CashierSaaSMetrics\Support\MetricResult::class);
    expect($result->value)->toBe(150.0);
});

it('normalizes yearly subscriptions to monthly MRR', function () {
    $subscriptions = Collection::make([
        [
            'id' => 'sub_1',
            'status' => 'active',
            'amount' => 1200, // Yearly
            'currency' => 'USD',
            'interval' => 'yearly',
            'created_at' => now()->subMonth(),
            'customer_id' => 'cust_1',
        ],
    ]);

    $this->mockProvider
        ->shouldReceive('getActiveSubscriptions')
        ->once()
        ->andReturn($subscriptions);

    $mrr = Metrics::mrr();
    $result = $mrr->calculate();

    // 1200 / 12 = 100 MRR
    expect($result->value)->toBe(100.0);
});

it('excludes trial subscriptions by default', function () {
    $subscriptions = Collection::make([
        [
            'id' => 'sub_1',
            'status' => 'active',
            'amount' => 100,
            'currency' => 'USD',
            'interval' => 'monthly',
            'created_at' => now()->subMonth(),
            'customer_id' => 'cust_1',
        ],
        [
            'id' => 'sub_2',
            'status' => 'trialing',
            'amount' => 50,
            'currency' => 'USD',
            'interval' => 'monthly',
            'trial_ends_at' => now()->addWeek(),
            'created_at' => now()->subWeek(),
            'customer_id' => 'cust_2',
        ],
    ]);

    $this->mockProvider
        ->shouldReceive('getActiveSubscriptions')
        ->once()
        ->andReturn($subscriptions);

    $mrr = Metrics::mrr();
    $result = $mrr->calculate();

    // Only active subscription, trial excluded
    expect($result->value)->toBe(100.0);
});

it('filters by plan', function () {
    $subscriptions = Collection::make([
        [
            'id' => 'sub_1',
            'status' => 'active',
            'amount' => 100,
            'currency' => 'USD',
            'interval' => 'monthly',
            'plan' => 'pro',
            'created_at' => now()->subMonth(),
            'customer_id' => 'cust_1',
        ],
        [
            'id' => 'sub_2',
            'status' => 'active',
            'amount' => 50,
            'currency' => 'USD',
            'interval' => 'monthly',
            'plan' => 'basic',
            'created_at' => now()->subMonth(),
            'customer_id' => 'cust_2',
        ],
    ]);

    $this->mockProvider
        ->shouldReceive('getActiveSubscriptions')
        ->once()
        ->andReturn($subscriptions);

    $mrr = Metrics::mrr()->plan('pro');
    $result = $mrr->calculate();

    expect($result->value)->toBe(100.0);
});

it('groups results by field', function () {
    $subscriptions = Collection::make([
        [
            'id' => 'sub_1',
            'status' => 'active',
            'amount' => 100,
            'currency' => 'USD',
            'interval' => 'monthly',
            'plan' => 'pro',
            'channel' => 'organic',
            'created_at' => now()->subMonth(),
            'customer_id' => 'cust_1',
        ],
        [
            'id' => 'sub_2',
            'status' => 'active',
            'amount' => 50,
            'currency' => 'USD',
            'interval' => 'monthly',
            'plan' => 'basic',
            'channel' => 'paid',
            'created_at' => now()->subMonth(),
            'customer_id' => 'cust_2',
        ],
    ]);

    $this->mockProvider
        ->shouldReceive('getActiveSubscriptions')
        ->once()
        ->andReturn($subscriptions);

    $mrr = Metrics::mrr()->groupBy('channel');
    $result = $mrr->calculate();

    expect($result)->toBeArray();
    expect($result)->toHaveKey('organic');
    expect($result)->toHaveKey('paid');
    expect($result['organic']['value'])->toBe(100.0);
    expect($result['paid']['value'])->toBe(50.0);
});
