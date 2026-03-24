# Cashier SaaS Metrics

[![Latest Version on Packagist](https://img.shields.io/packagist/v/plusinfolab/cashier-saas-metrics.svg?style=flat-square)](https://packagist.org/packages/plusinfolab/cashier-saas-metrics)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/plusinfolab/cashier-saas-metrics/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/plusinfolab/cashier-saas-metrics/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/plusinfolab/cashier-saas-metrics/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/plusinfolab/cashier-saas-metrics/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/plusinfolab/cashier-saas-metrics.svg?style=flat-square)](https://packagist.org/packages/plusinfolab/cashier-saas-metrics)

A comprehensive SaaS metrics analyzer for Laravel that provides actionable insights for subscription-based businesses. Track MRR, churn rate, LTV, ARPU, and cohort analysis with support for Stripe, Paddle, and custom billing providers.

## Highlights

- 📊 **Pre-built Metric Calculators** - MRR, churn rate, LTV, ARPU, and cohort analysis
- 🔌 **Multi-Provider Support** - Works with Stripe, Paddle, LemonSqueezy, or custom billing
- ⚡ **Cache-First Architecture** - Intelligent caching with automatic invalidation
- 🎨 **Dashboard Components** - Ready-to-use Blade components for Laravel
- 📈 **Cohort Analysis** - First-class cohort tracking with retention curves
- 🔄 **Auto Recalculation** - Background jobs keep metrics fresh

## Installation

You can install the package via composer:

```bash
composer require plusinfolab/cashier-saas-metrics
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="saas-metrics-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="saas-metrics-config"
```

This is the contents of the published config file:

```php
return [
    'provider' => env('SAAS_METRICS_PROVIDER', 'stripe'),
    'base_currency' => env('SAAS_METRICS_BASE_CURRENCY', 'USD'),
    // ... other configuration
];
```

## Usage

### Basic Metrics

```php
use PlusInfoLab\CashierSaaSMetrics\Facades\Metrics;

// Get current MRR
$mrr = Metrics::mrr()->calculate();
echo $mrr->formattedAsCurrency(); // "$42,500.00"

// Get churn rate
$churnRate = Metrics::churnRate()->calculate();
echo $churnRate->formattedAsPercentage(); // "4.25%"

// Get LTV
$ltv = Metrics::lifetimeValue()->calculate();
echo $ltv->formattedAsCurrency(); // "$1,200.00"

// Get ARPU
$arpu = Metrics::arpu()->calculate();
echo $arpu->formattedAsCurrency(); // "$85.00"
```

### Filtering and Grouping

```php
// Filter by period
$mrrThisMonth = Metrics::mrr()
    ->period('last_month')
    ->calculate();

// Filter by plan
$proMrr = Metrics::mrr()
    ->plan('pro')
    ->calculate();

// Group by field
$mrrByPlan = Metrics::mrr()
    ->groupBy('plan')
    ->calculate();

// Filter by currency
$usdMrr = Metrics::mrr()
    ->currency('USD')
    ->calculate();
```

### Cohort Analysis

```php
$cohorts = Metrics::cohorts()
    ->period('last_6_months')
    ->by('signup_month')
    ->retentionMetric('mrr')
    ->calculate();

// Returns cohort data with retention curves
foreach ($cohorts->value as $cohortKey => $cohortData) {
    foreach ($cohortData['periods'] as $period) {
        echo "Period {$period['period']}: {$period['retention_percentage']}% retention\n";
    }
}
```

### Dashboard Components

```blade
<x-saas-metrics::metrics-panel title="SaaS Dashboard" />
```

Or use individual metric cards:

```blade
<x-saas-metrics::metric-card
    :title="'Monthly Recurring Revenue'"
    :value="$mrr"
    icon="heroicon-o-currency-dollar"
    color="green"
/>
```

### All Metrics at Once

```php
$dashboard = Metrics::dashboard('this_month');

/*
[
    'mrr' => MetricResult(42500),
    'churn_rate' => MetricResult(0.0425),
    'ltv' => MetricResult(1200),
    'arpu' => MetricResult(85),
    'period' => 'this_month',
    'currency' => 'USD',
]
*/
```

## Subscription Providers

### Stripe (using Laravel Cashier)

```php
// config/saas-metrics.php
'provider' => 'stripe',

'providers' => [
    'stripe' => [
        'api_key' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
],
```

### Paddle

```php
'provider' => 'paddle',

'providers' => [
    'paddle' => [
        'vendor_id' => env('PADDLE_VENDOR_ID'),
        'auth_code' => env('PADDLE_AUTH_CODE'),
    ],
],
```

### Custom Provider

```php
'provider' => 'custom',

'providers' => [
    'custom' => [
        'models' => [
            'subscription' => App\Models\Subscription::class,
            'payment' => App\Models\Payment::class,
        ],
        'field_map' => [
            'id' => 'id',
            'status' => 'status',
            'amount' => 'amount',
            // ... map your model fields
        ],
    ],
],
```

## Cache Invalidation

The package automatically invalidates cache when subscription events occur. You can dispatch events manually:

```php
use PlusInfoLab\CashierSaaSMetrics\Events\SubscriptionCreated;
use PlusInfoLab\CashierSaaSMetrics\Events\SubscriptionCancelled;
use PlusInfoLab\CashierSaaSMetrics\Events\SubscriptionUpdated;

// When a subscription is created
event(new SubscriptionCreated($subscriptionId, $customerId));

// When a subscription is cancelled
event(new SubscriptionCancelled($subscriptionId, $customerId));

// When a subscription is updated
event(new SubscriptionUpdated($subscriptionId, $customerId, $changes));
```

Or clear cache manually:

```php
// Clear all metrics cache
Metrics::clearCache();

// Clear specific metric cache
Metrics::clearMetricCache('mrr');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [PlusInfoLab](https://github.com/plusinfolab)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
