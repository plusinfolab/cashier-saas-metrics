<?php

namespace PlusInfoLab\CashierSaaSMetrics\Providers\Adapters;

use Illuminate\Support\Collection;

class PaddleProvider extends AbstractSubscriptionProvider
{
    /**
     * Get all active subscriptions from Paddle.
     */
    public function getActiveSubscriptions(): Collection
    {
        try {
            $response = $this->makeRequest('POST', '/api/2.0/subscription/users', [
                'json' => [
                    'state' => 'active',
                    'limit' => 200,
                ],
            ]);

            return collect($response['response'] ?? [])
                ->map(function (array $sub) {
                    return $this->normalizePaddleSubscription($sub);
                });
        } catch (\Exception $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Get cancelled subscriptions within a period.
     */
    public function getCancelledSubscriptions(\DateTimeInterface $start, \DateTimeInterface $end): Collection
    {
        try {
            $response = $this->makeRequest('POST', '/api/2.0/subscription/users', [
                'json' => [
                    'state' => 'deleted',
                    'limit' => 200,
                ],
            ]);

            return collect($response['response'] ?? [])
                ->filter(function (array $sub) use ($start, $end) {
                    $cancelledAt = \Illuminate\Support\Carbon::parse($sub['cancelled_at'] ?? $sub['updated_at']);
                    return $cancelledAt->between($start, $end);
                })
                ->map(function (array $sub) {
                    return $this->normalizePaddleSubscription($sub);
                });
        } catch (\Exception $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Get new subscriptions within a period.
     */
    public function getNewSubscriptions(\DateTimeInterface $start, \DateTimeInterface $end): Collection
    {
        try {
            $response = $this->makeRequest('POST', '/api/2.0/subscription/users', [
                'json' => [
                    'state' => 'active',
                    'limit' => 200,
                ],
            ]);

            return collect($response['response'] ?? [])
                ->filter(function (array $sub) use ($start, $end) {
                    $createdAt = \Illuminate\Support\Carbon::parse($sub['sign_up_date']);
                    return $createdAt->between($start, $end);
                })
                ->map(function (array $sub) {
                    return $this->normalizePaddleSubscription($sub);
                });
        } catch (\Exception $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Get subscription by ID.
     */
    public function getSubscription(string|int $subscriptionId): ?array
    {
        try {
            $response = $this->makeRequest('POST', '/api/2.0/subscription/users', [
                'json' => [
                    'subscription_id' => $subscriptionId,
                ],
            ]);

            if (isset($response['response'][0])) {
                return $this->normalizePaddleSubscription($response['response'][0]);
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get payments/invoices within a period.
     */
    public function getPayments(\DateTimeInterface $start, \DateTimeInterface $end): Collection
    {
        try {
            $response = $this->makeRequest('POST', '/api/2.0/payment', [
                'json' => [
                    'from' => $start->format('Y-m-d'),
                    'to' => $end->format('Y-m-d'),
                    'is_paid' => '1',
                    'limit' => 200,
                ],
            ]);

            return collect($response['response'] ?? [])
                ->map(function (array $payment) {
                    return [
                        'id' => $payment['id'],
                        'amount' => (float) $payment['amount'],
                        'currency' => strtoupper($payment['currency'] ?? 'usd'),
                        'status' => $payment['status'] ?? 'paid',
                        'subscription_id' => $payment['subscription_id'] ?? null,
                        'customer_id' => $payment['customer_id'] ?? null,
                        'created_at' => \Illuminate\Support\Carbon::parse($payment['payment_date'] ?? $payment['created_at']),
                    ];
                });
        } catch (\Exception $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Get payments for a specific subscription.
     */
    public function getSubscriptionPayments(string|int $subscriptionId): Collection
    {
        try {
            $response = $this->makeRequest('POST', '/api/2.0/payment', [
                'json' => [
                    'subscription_id' => $subscriptionId,
                    'is_paid' => '1',
                    'limit' => 200,
                ],
            ]);

            return collect($response['response'] ?? [])
                ->map(function (array $payment) {
                    return [
                        'id' => $payment['id'],
                        'amount' => (float) $payment['amount'],
                        'currency' => strtoupper($payment['currency'] ?? 'usd'),
                        'status' => $payment['status'] ?? 'paid',
                        'subscription_id' => $payment['subscription_id'] ?? null,
                        'customer_id' => $payment['customer_id'] ?? null,
                        'created_at' => \Illuminate\Support\Carbon::parse($payment['payment_date'] ?? $payment['created_at']),
                    ];
                });
        } catch (\Exception $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Normalize Paddle subscription data.
     */
    protected function normalizePaddleSubscription(array $subscription): array
    {
        $planAmount = (float) ($subscription['plan_amount'] ?? 0);
        $planCurrency = strtoupper($subscription['plan_currency'] ?? 'usd');
        $planInterval = $subscription['plan_interval'] ?? 'month';

        return [
            'id' => $subscription['subscription_id'] ?? $subscription['id'],
            'status' => $subscription['state'] ?? 'unknown',
            'plan_id' => $subscription['plan_id'] ?? null,
            'amount' => $planAmount,
            'currency' => $planCurrency,
            'interval' => $planInterval,
            'quantity' => 1,
            'created_at' => \Illuminate\Support\Carbon::parse($subscription['sign_up_date'] ?? $subscription['created_at']),
            'updated_at' => isset($subscription['updated_at']) ? \Illuminate\Support\Carbon::parse($subscription['updated_at']) : null,
            'cancelled_at' => isset($subscription['cancelled_at']) ? \Illuminate\Support\Carbon::parse($subscription['cancelled_at']) : null,
            'next_payment_date' => isset($subscription['next_payment_date']) ? \Illuminate\Support\Carbon::parse($subscription['next_payment_date']) : null,
            'customer_id' => $subscription['customer_id'] ?? null,
            'user_id' => $subscription['user_id'] ?? null,
            'email' => $subscription['user_email'] ?? null,
            'metadata' => $subscription['custom_data'] ?? [],
        ];
    }

    /**
     * Get API headers for Paddle.
     */
    protected function getApiHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Make a Paddle API request.
     */
    protected function makeRequest(string $method, string $url, array $options = []): array
    {
        $vendorId = $this->config['vendor_id'] ?? '';
        $authCode = $this->config['auth_code'] ?? '';

        // Add vendor auth code to request
        if (! isset($options['json'])) {
            $options['json'] = [];
        }

        $options['json']['vendor_id'] = $vendorId;
        $options['json']['vendor_auth_code'] = $authCode;

        return parent::makeRequest($method, $url, $options);
    }
}
