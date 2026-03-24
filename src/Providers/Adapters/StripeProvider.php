<?php

namespace PlusInfoLab\CashierSaaSMetrics\Providers\Adapters;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Cashier\Subscription as CashierSubscription;

class StripeProvider extends AbstractSubscriptionProvider
{
    /**
     * Get all active subscriptions from Stripe/Cashier.
     */
    public function getActiveSubscriptions(): Collection
    {
        if (class_exists(CashierSubscription::class)) {
            return $this->getCashierSubscriptions();
        }

        return $this->getStripeSubscriptions();
    }

    /**
     * Get subscriptions from Laravel Cashier.
     */
    protected function getCashierSubscriptions(): Collection
    {
        return CashierSubscription::query()
            ->with('owner')
            ->whereNotNull('stripe_id')
            ->where(function ($query) {
                $query->where('stripe_status', 'active')
                    ->orWhere('stripe_status', 'trialing');
            })
            ->get()
            ->map(function (CashierSubscription $subscription) {
                return [
                    'id' => $subscription->stripe_id,
                    'local_id' => $subscription->id,
                    'status' => $subscription->stripe_status,
                    'plan_id' => $subscription->stripe_price,
                    'amount' => $this->getPriceAmount($subscription),
                    'currency' => $this->getPriceCurrency($subscription),
                    'interval' => $this->getPriceInterval($subscription),
                    'quantity' => $subscription->quantity ?? 1,
                    'created_at' => $subscription->created_at,
                    'ends_at' => $subscription->ends_at,
                    'cancelled_at' => $subscription->cancelled_at,
                    'trial_ends_at' => $subscription->trial_ends_at,
                    'customer_id' => $subscription->user_id ?? $subscription->owner?->id,
                    'metadata' => $subscription->metadata ?? [],
                ];
            });
    }

    /**
     * Get subscriptions directly from Stripe API.
     */
    protected function getStripeSubscriptions(): Collection
    {
        try {
            $response = $this->makeRequest('GET', '/v1/subscriptions', [
                'query' => [
                    'status' => 'active',
                    'limit' => 100,
                ],
            ]);

            return collect($response['data'] ?? [])
                ->map(function (array $sub) {
                    return $this->normalizeStripeSubscription($sub);
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
        if (class_exists(CashierSubscription::class)) {
            return CashierSubscription::query()
                ->with('owner')
                ->whereNotNull('stripe_id')
                ->whereNotNull('cancelled_at')
                ->whereBetween('cancelled_at', [$start, $end])
                ->get()
                ->map(function (CashierSubscription $subscription) {
                    return [
                        'id' => $subscription->stripe_id,
                        'local_id' => $subscription->id,
                        'status' => $subscription->stripe_status,
                        'plan_id' => $subscription->stripe_price,
                        'amount' => $this->getPriceAmount($subscription),
                        'currency' => $this->getPriceCurrency($subscription),
                        'interval' => $this->getPriceInterval($subscription),
                        'quantity' => $subscription->quantity ?? 1,
                        'created_at' => $subscription->created_at,
                        'cancelled_at' => $subscription->cancelled_at,
                        'customer_id' => $subscription->user_id ?? $subscription->owner?->id,
                        'metadata' => $subscription->metadata ?? [],
                    ];
                });
        }

        try {
            $response = $this->makeRequest('GET', '/v1/subscriptions', [
                'query' => [
                    'status' => 'canceled',
                    'limit' => 100,
                ],
            ]);

            return collect($response['data'] ?? [])
                ->filter(function (array $sub) use ($start, $end) {
                    $cancelledAt = Carbon::parse($sub['canceled_at']);

                    return $cancelledAt->between($start, $end);
                })
                ->map(function (array $sub) {
                    return $this->normalizeStripeSubscription($sub);
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
        if (class_exists(CashierSubscription::class)) {
            return CashierSubscription::query()
                ->with('owner')
                ->whereNotNull('stripe_id')
                ->whereBetween('created_at', [$start, $end])
                ->get()
                ->map(function (CashierSubscription $subscription) {
                    return [
                        'id' => $subscription->stripe_id,
                        'local_id' => $subscription->id,
                        'status' => $subscription->stripe_status,
                        'plan_id' => $subscription->stripe_price,
                        'amount' => $this->getPriceAmount($subscription),
                        'currency' => $this->getPriceCurrency($subscription),
                        'interval' => $this->getPriceInterval($subscription),
                        'quantity' => $subscription->quantity ?? 1,
                        'created_at' => $subscription->created_at,
                        'customer_id' => $subscription->user_id ?? $subscription->owner?->id,
                        'metadata' => $subscription->metadata ?? [],
                    ];
                });
        }

        try {
            $response = $this->makeRequest('GET', '/v1/subscriptions', [
                'query' => [
                    'status' => 'active',
                    'limit' => 100,
                ],
            ]);

            return collect($response['data'] ?? [])
                ->filter(function (array $sub) use ($start, $end) {
                    $createdAt = Carbon::parse($sub['created']);

                    return $createdAt->between($start, $end);
                })
                ->map(function (array $sub) {
                    return $this->normalizeStripeSubscription($sub);
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
        if (class_exists(CashierSubscription::class)) {
            $subscription = CashierSubscription::where('stripe_id', $subscriptionId)->first();

            if ($subscription) {
                return [
                    'id' => $subscription->stripe_id,
                    'local_id' => $subscription->id,
                    'status' => $subscription->stripe_status,
                    'plan_id' => $subscription->stripe_price,
                    'amount' => $this->getPriceAmount($subscription),
                    'currency' => $this->getPriceCurrency($subscription),
                    'interval' => $this->getPriceInterval($subscription),
                    'quantity' => $subscription->quantity ?? 1,
                    'created_at' => $subscription->created_at,
                    'customer_id' => $subscription->user_id ?? $subscription->owner?->id,
                    'metadata' => $subscription->metadata ?? [],
                ];
            }
        }

        try {
            $response = $this->makeRequest('GET', "/v1/subscriptions/{$subscriptionId}");

            return $this->normalizeStripeSubscription($response);
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
            $response = $this->makeRequest('GET', '/v1/invoices', [
                'query' => [
                    'status' => 'paid',
                    'limit' => 100,
                ],
            ]);

            return collect($response['data'] ?? [])
                ->filter(function (array $invoice) use ($start, $end) {
                    $createdAt = Carbon::parse($invoice['created']);

                    return $createdAt->between($start, $end);
                })
                ->map(function (array $invoice) {
                    return [
                        'id' => $invoice['id'],
                        'amount' => $invoice['total'] / 100, // Convert from cents
                        'currency' => strtoupper($invoice['currency']),
                        'status' => $invoice['status'],
                        'subscription_id' => $invoice['subscription'] ?? null,
                        'customer_id' => $invoice['customer'] ?? null,
                        'created_at' => Carbon::parse($invoice['created']),
                        'paid_at' => isset($invoice['status_transitions']['paid_at'])
                            ? Carbon::parse($invoice['status_transitions']['paid_at'])
                            : null,
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
            $response = $this->makeRequest('GET', '/v1/invoices', [
                'query' => [
                    'subscription' => $subscriptionId,
                    'status' => 'paid',
                    'limit' => 100,
                ],
            ]);

            return collect($response['data'] ?? [])
                ->map(function (array $invoice) {
                    return [
                        'id' => $invoice['id'],
                        'amount' => $invoice['total'] / 100,
                        'currency' => strtoupper($invoice['currency']),
                        'status' => $invoice['status'],
                        'subscription_id' => $invoice['subscription'] ?? null,
                        'customer_id' => $invoice['customer'] ?? null,
                        'created_at' => Carbon::parse($invoice['created']),
                    ];
                });
        } catch (\Exception $e) {
            report($e);

            return collect();
        }
    }

    /**
     * Normalize Stripe subscription data.
     */
    protected function normalizeStripeSubscription(array $subscription): array
    {
        $price = $subscription['items']['data'][0]['price'] ?? [];

        return [
            'id' => $subscription['id'],
            'status' => $subscription['status'],
            'plan_id' => $price['id'] ?? null,
            'amount' => ($price['unit_amount'] ?? 0) / 100,
            'currency' => strtoupper($price['currency'] ?? 'usd'),
            'interval' => $price['recurring']['interval'] ?? 'month',
            'interval_count' => $price['recurring']['interval_count'] ?? 1,
            'quantity' => $subscription['items']['data'][0]['quantity'] ?? 1,
            'created_at' => Carbon::parse($subscription['created']),
            'current_period_start' => Carbon::parse($subscription['current_period_start']),
            'current_period_end' => Carbon::parse($subscription['current_period_end']),
            'cancel_at_period_end' => $subscription['cancel_at_period_end'] ?? false,
            'canceled_at' => isset($subscription['canceled_at']) ? Carbon::parse($subscription['canceled_at']) : null,
            'trial_start' => isset($subscription['trial_start']) ? Carbon::parse($subscription['trial_start']) : null,
            'trial_end' => isset($subscription['trial_end']) ? Carbon::parse($subscription['trial_end']) : null,
            'customer_id' => $subscription['customer'] ?? null,
            'metadata' => $subscription['metadata'] ?? [],
        ];
    }

    /**
     * Get price amount from Cashier subscription.
     */
    protected function getPriceAmount(CashierSubscription $subscription): float
    {
        if (isset($subscription->items->first()->price)) {
            return $subscription->items->first()->price->unit_amount / 100;
        }

        return 0;
    }

    /**
     * Get price currency from Cashier subscription.
     */
    protected function getPriceCurrency(CashierSubscription $subscription): string
    {
        if (isset($subscription->items->first()->price)) {
            return strtoupper($subscription->items->first()->price->currency);
        }

        return 'USD';
    }

    /**
     * Get price interval from Cashier subscription.
     */
    protected function getPriceInterval(CashierSubscription $subscription): string
    {
        if (isset($subscription->items->first()->price)) {
            return $subscription->items->first()->price->recurring_interval ?? 'month';
        }

        return 'month';
    }

    /**
     * Get API base URL.
     */
    protected function getApiBaseUrl(): string
    {
        return 'https://api.stripe.com';
    }
}
