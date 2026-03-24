<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Subscription Provider
    |--------------------------------------------------------------------------
    |
    | The default subscription provider to use for fetching subscription data.
    | Supported providers: 'stripe', 'paddle', 'lemonsqueezy', 'custom'
    |
    */

    'provider' => env('SAAS_METRICS_PROVIDER', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Base Currency
    |--------------------------------------------------------------------------
    |
    | The base currency to use for all metric calculations. Amounts from
    | other currencies will be converted to this currency.
    |
    */

    'base_currency' => env('SAAS_METRICS_BASE_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    |
    | Configuration for each subscription provider.
    |
    */

    'providers' => [

        'stripe' => [
            'api_key' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'api_url' => 'https://api.stripe.com',
        ],

        'paddle' => [
            'vendor_id' => env('PADDLE_VENDOR_ID'),
            'auth_code' => env('PADDLE_AUTH_CODE'),
            'api_url' => 'https://vendors.paddle.com',
        ],

        'lemonsqueezy' => [
            'api_key' => env('LEMONSQUEEZY_API_KEY'),
            'store_id' => env('LEMONSQUEEZY_STORE_ID'),
            'api_url' => 'https://api.lemonsqueezy.com',
        ],

        'custom' => [
            /*
             | Model configurations for custom provider
             |
             | 'subscription' - Your Eloquent subscription model class
             | 'payment' - Your Eloquent payment/invoice model class
             |
             */
            'models' => [
                'subscription' => env('SAAS_METRICS_CUSTOM_SUBSCRIPTION_MODEL'),
                'payment' => env('SAAS_METRICS_CUSTOM_PAYMENT_MODEL'),
            ],

            /*
             | Field mappings for custom subscription model
             |
             | Map your model's fields to the expected field names.
             |
             */
            'field_map' => [
                'id' => 'id',
                'status' => 'status',
                'plan_id' => 'plan_id',
                'amount' => 'amount',
                'currency' => 'currency',
                'interval' => 'interval',
                'quantity' => 'quantity',
                'created_at' => 'created_at',
                'cancelled_at' => 'cancelled_at',
                'trial_ends_at' => 'trial_ends_at',
                'customer_id' => 'user_id',
            ],

            /*
             | Payment model configuration
             |
             */
            'payment_date_field' => 'created_at',
            'payment_amount_field' => 'amount',
            'payment_currency_field' => 'currency',
            'payment_subscription_field' => 'subscription_id',
            'payment_customer_field' => 'customer_id',
            'metadata_field' => 'metadata',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for metric calculations.
    |
    */

    'cache' => [
        'enabled' => env('SAAS_METRICS_CACHE_ENABLED', true),

        'ttl' => [
            'short' => env('SAAS_METRICS_CACHE_TTL_SHORT', 300), // 5 minutes
            'medium' => env('SAAS_METRICS_CACHE_TTL_MEDIUM', 600), // 10 minutes
            'long' => env('SAAS_METRICS_CACHE_TTL_LONG', 3600), // 1 hour
        ],

        'precalculate' => env('SAAS_METRICS_CACHE_PRECALCULATE', false),

        'precalculate_metrics' => [
            'mrr',
            'churn_rate',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Exchange Rates
    |--------------------------------------------------------------------------
    |
    | Manual exchange rates for currency conversion.
    | Format: 'FROM_CURRENCY' => CONVERSION_RATE
    |
    | Example: 'EUR' => 1.1 means 1 EUR = 1.1 USD (when base_currency is USD)
    |
    | For automatic rates, consider using the exchangerate API package.
    |
    */

    'exchange_rates' => [
        // 'EUR' => 1.1,
        // 'GBP' => 1.25,
        // 'CAD' => 0.75,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Recalculate
    |--------------------------------------------------------------------------
    |
    | Automatically recalculate metrics when subscription events occur.
    |
    */

    'auto_recalculate' => env('SAAS_METRICS_AUTO_RECALCULATE', true),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | The queue to use for metric recalculation jobs.
    |
    */

    'queue' => env('SAAS_METRICS_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for dashboard components and widgets.
    |
    */

    'dashboard' => [
        'currency_symbol' => '$',
        'decimal_places' => 2,
        'thousand_separator' => ',',
        'date_format' => 'M Y',
    ],

];
