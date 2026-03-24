<?php

namespace PlusInfoLab\CashierSaaSMetrics\Providers\Adapters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CustomProvider extends AbstractSubscriptionProvider
{
    /**
     * The custom subscription model.
     */
    protected ?string $subscriptionModel;

    /**
     * The custom payment/invoice model.
     */
    protected ?string $paymentModel;

    /**
     * Field mappings for the subscription model.
     */
    protected array $fieldMap = [
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
    ];

    public function __construct(array $config, string $baseCurrency)
    {
        parent::__construct($config, $baseCurrency);

        $this->subscriptionModel = $config['models']['subscription'] ?? null;
        $this->paymentModel = $config['models']['payment'] ?? null;
        $this->fieldMap = array_merge($this->fieldMap, $config['field_map'] ?? []);
    }

    /**
     * Get all active subscriptions.
     */
    public function getActiveSubscriptions(): Collection
    {
        if ($this->subscriptionModel === null || ! class_exists($this->subscriptionModel)) {
            return collect();
        }

        $model = new $this->subscriptionModel;

        return $model->newQuery()
            ->whereNotNull($this->fieldMap['customer_id'])
            ->where(function ($query) {
                $query->where($this->fieldMap['status'], 'active')
                    ->orWhere($this->fieldMap['status'], 'trialing');
            })
            ->get()
            ->map(fn (Model $sub) => $this->normalizeSubscription($sub));
    }

    /**
     * Get cancelled subscriptions within a period.
     */
    public function getCancelledSubscriptions(\DateTimeInterface $start, \DateTimeInterface $end): Collection
    {
        if ($this->subscriptionModel === null || ! class_exists($this->subscriptionModel)) {
            return collect();
        }

        $model = new $this->subscriptionModel;

        return $model->newQuery()
            ->whereNotNull($this->fieldMap['cancelled_at'])
            ->whereBetween($this->fieldMap['cancelled_at'], [$start, $end])
            ->get()
            ->map(fn (Model $sub) => $this->normalizeSubscription($sub));
    }

    /**
     * Get new subscriptions within a period.
     */
    public function getNewSubscriptions(\DateTimeInterface $start, \DateTimeInterface $end): Collection
    {
        if ($this->subscriptionModel === null || ! class_exists($this->subscriptionModel)) {
            return collect();
        }

        $model = new $this->subscriptionModel;

        return $model->newQuery()
            ->whereBetween($this->fieldMap['created_at'], [$start, $end])
            ->get()
            ->map(fn (Model $sub) => $this->normalizeSubscription($sub));
    }

    /**
     * Get subscription by ID.
     */
    public function getSubscription(string|int $subscriptionId): ?array
    {
        if ($this->subscriptionModel === null || ! class_exists($this->subscriptionModel)) {
            return null;
        }

        $model = new $this->subscriptionModel;

        $subscription = $model->newQuery()
            ->where($this->fieldMap['id'], $subscriptionId)
            ->first();

        if ($subscription === null) {
            return null;
        }

        return $this->normalizeSubscription($subscription);
    }

    /**
     * Get payments/invoices within a period.
     */
    public function getPayments(\DateTimeInterface $start, \DateTimeInterface $end): Collection
    {
        if ($this->paymentModel === null || ! class_exists($this->paymentModel)) {
            return collect();
        }

        $model = new $this->paymentModel;

        $dateField = $this->config['payment_date_field'] ?? 'created_at';

        return $model->newQuery()
            ->whereBetween($dateField, [$start, $end])
            ->get()
            ->map(fn (Model $payment) => $this->normalizePayment($payment, $dateField));
    }

    /**
     * Get payments for a specific subscription.
     */
    public function getSubscriptionPayments(string|int $subscriptionId): Collection
    {
        if ($this->paymentModel === null || ! class_exists($this->paymentModel)) {
            return collect();
        }

        $model = new $this->paymentModel;

        $subscriptionField = $this->config['payment_subscription_field'] ?? 'subscription_id';

        return $model->newQuery()
            ->where($subscriptionField, $subscriptionId)
            ->get()
            ->map(fn (Model $payment) => $this->normalizePayment($payment));
    }

    /**
     * Normalize subscription model to array.
     */
    protected function normalizeSubscription(Model $subscription): array
    {
        $map = $this->fieldMap;

        return [
            'id' => $subscription->getAttribute($map['id']),
            'status' => $subscription->getAttribute($map['status']),
            'plan_id' => $subscription->getAttribute($map['plan_id']),
            'plan' => $subscription->getAttribute($map['plan_id']), // Alias for plan_id
            'amount' => (float) $subscription->getAttribute($map['amount']),
            'currency' => strtoupper($subscription->getAttribute($map['currency']) ?? 'usd'),
            'interval' => $subscription->getAttribute($map['interval']) ?? 'month',
            'quantity' => (int) $subscription->getAttribute($map['quantity']) ?? 1,
            'created_at' => $subscription->getAttribute($map['created_at']),
            'cancelled_at' => $subscription->getAttribute($map['cancelled_at']),
            'trial_ends_at' => $subscription->getAttribute($map['trial_ends_at']),
            'customer_id' => $subscription->getAttribute($map['customer_id']),
            'metadata' => $this->extractMetadata($subscription),
        ];
    }

    /**
     * Normalize payment model to array.
     */
    protected function normalizePayment(Model $payment, string $dateField = 'created_at'): array
    {
        $amountField = $this->config['payment_amount_field'] ?? 'amount';
        $currencyField = $this->config['payment_currency_field'] ?? 'currency';
        $subscriptionField = $this->config['payment_subscription_field'] ?? 'subscription_id';
        $customerField = $this->config['payment_customer_field'] ?? 'customer_id';

        return [
            'id' => $payment->getKey(),
            'amount' => (float) $payment->getAttribute($amountField),
            'currency' => strtoupper($payment->getAttribute($currencyField) ?? 'usd'),
            'status' => 'paid',
            'subscription_id' => $payment->getAttribute($subscriptionField),
            'customer_id' => $payment->getAttribute($customerField),
            'created_at' => $payment->getAttribute($dateField),
        ];
    }

    /**
     * Extract metadata from subscription model.
     */
    protected function extractMetadata(Model $subscription): array
    {
        $metadataField = $this->config['metadata_field'] ?? 'metadata';

        $metadata = $subscription->getAttribute($metadataField);

        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata)) {
            return json_decode($metadata, true) ?? [];
        }

        return [];
    }
}
