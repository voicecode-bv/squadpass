<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\MollieCustomer;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Mollie\MollieGateway;

class ChargeSubscription
{
    public function __construct(protected MollieGateway $gateway) {}

    /**
     * Charge one due subscription via its mandate and advance the billing date.
     */
    public function handle(Subscription $subscription): ?Payment
    {
        if ($subscription->status !== SubscriptionStatus::Active || ! $subscription->mollie_mandate_id) {
            return null;
        }

        $plan = $subscription->plan;
        $club = $subscription->club;

        $customerId = MollieCustomer::query()
            ->where('club_id', $club->id)
            ->where('user_id', $subscription->user_id)
            ->value('mollie_customer_id');

        if (! $customerId) {
            return null;
        }

        // Advance the billing date first so a slow webhook can never cause a double
        // charge, and refill the lesson credits for the new period.
        $subscription->update([
            'next_billing_at' => $plan->interval->addTo($subscription->next_billing_at ?? today()),
            'credits_remaining' => $plan->credits,
        ]);

        $result = $this->gateway->createPayment(
            club: $club,
            amountCents: $plan->price_cents,
            description: "{$club->name} — {$plan->name} ({$plan->interval->label()})",
            redirectUrl: route('portal.subscriptions'),
            webhookUrl: route('webhooks.mollie', $club),
            customerId: $customerId,
            sequenceType: 'recurring',
            mandateId: $subscription->mollie_mandate_id,
            metadata: [
                'purpose' => 'subscription_recurring',
                'subscription_id' => $subscription->id,
            ],
        );

        return Payment::create([
            'club_id' => $club->id,
            'user_id' => $subscription->user_id,
            'payable_type' => Subscription::class,
            'payable_id' => $subscription->id,
            'mollie_payment_id' => $result->id,
            'status' => PaymentStatus::Open,
            'amount_cents' => $plan->price_cents,
            'currency' => $club->currency,
            'metadata' => ['purpose' => 'subscription_recurring'],
        ]);
    }
}
