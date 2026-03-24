# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Cashier SaaS Metrics** is a Laravel package for tracking SaaS metrics (MRR, churn, LTV, ARPU, cohort analysis) with support for multiple subscription billing providers (Stripe, Paddle, LemonSqueezy, custom).

The package uses a **calculator-based architecture** where each metric type has its own calculator class that implements the `MetricCalculator` contract. Calculators fetch data through a **subscription provider adapter** that abstracts away differences between billing platforms.

## Development Commands

### Testing
- `composer test` — Run all tests using Pest
- `vendor/bin/pest tests/Unit/MRRTest.php` — Run specific test file
- `vendor/bin/pest --coverage` — Run tests with coverage

### Code Quality
- `composer format` — Fix code style using Laravel Pint
- `composer analyse` — Run PHPStan static analysis (level 5)

### Setup
- `composer run prepare` — Discover Testbench package (runs on autoload-dump)

## Architecture

### Core Concepts

1. **Subscription Providers** (`src/Providers/Adapters/`) - Abstract data sources
   - `StripeProvider` - Uses Laravel Cashier or direct Stripe API
   - `PaddleProvider` - Paddle billing integration
   - `CustomProvider` - Works with any Eloquent subscription/payment models
   - All implement `SubscriptionProvider` contract

2. **Metric Calculators** (`src/Metrics/`) - Calculate specific metrics
   - `MRR` - Monthly Recurring Revenue with normalization
   - `ChurnRate` - Net/gross churn rate calculation
   - `LifetimeValue` - LTV using ARPU/churn formula
   - `ARPU` - Average Revenue Per User
   - `CohortAnalysis` - Retention curves by cohort
   - All extend `AbstractMetricCalculator`

3. **Cache Layer** (`src/Cache/`) - Tag-based caching
   - `MetricsCache` - Wrapper around Laravel cache with tag support
   - Automatic invalidation via events
   - Different TTLs: short (5min), medium (10min), long (1hr)

4. **Events & Jobs** (`src/Events/`, `src/Jobs/`)
   - `SubscriptionCreated/Created/Updated` - Trigger cache invalidation
   - `RecalculateMetricsJob` - Background metric recalculation
   - `CalculateMetricJob` - Calculate specific metric in background

### Key Data Flow

```
Metrics Facade
    ↓
Metrics Manager
    ↓
Metric Calculator (MRR, ChurnRate, etc.)
    ↓
Subscription Provider (Stripe, Paddle, Custom)
    ↓
Metrics Cache (remembers results)
```

### Configuration Structure

- `provider` - Default subscription provider (stripe/paddle/custom)
- `base_currency` - All metrics converted to this currency
- `providers.*` - Provider-specific API credentials
- `cache.ttl.*` - Cache duration for different metric types
- `exchange_rates` - Manual currency conversion rates

### Adding a New Metric

1. Create calculator class in `src/Metrics/`
2. Extend `AbstractMetricCalculator`
3. Implement `calculate()` method
4. Add facade method in `MetricsManager`
5. Add to `getCalculator()` switch statement

### Adding a New Provider

1. Create adapter in `src/Providers/Adapters/`
2. Extend `AbstractSubscriptionProvider`
3. Implement all contract methods
4. Add factory method in `SubscriptionProviderFactory`
5. Document in README.md

## Important Patterns

### Calculator Chaining

All calculators support method chaining for filters:

```php
Metrics::mrr()
    ->period('last_month')
    ->plan('pro')
    ->currency('USD')
    ->groupBy('acquisition_channel')
    ->calculate();
```

### Currency Handling

- All amounts normalized to base currency
- Yearly/quarterly subscriptions converted to monthly
- Manual exchange rates in config (or implement API-based conversion)

### Event-Driven Cache Invalidation

The package listens for subscription events and dispatches recalculation jobs automatically. Enable/disable via `saas-metrics.auto_recalculate` config.

## Testing Notes

- Unit tests mock the `SubscriptionProvider` contract
- Use `Collection::make()` for test data
- Test both individual calculations and edge cases (zero values, missing data)
- Test grouping and filtering functionality

## Code Style

- PSR-4 autoloading: `PlusInfoLab\CashierSaaSMetrics\`
- 4 spaces for indentation
- Laravel Pint enforces style automatically
- Use type hints on all public methods
- Return `MetricResult` objects from calculate() methods
