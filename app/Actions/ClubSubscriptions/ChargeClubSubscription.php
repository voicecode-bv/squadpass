<?php

namespace App\Actions\ClubSubscriptions;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\ClubSubscription;
use App\Models\ClubSubscriptionPayment;
use App\Services\Mollie\MollieGateway;
use App\Services\Mollie\PlatformMollieAccount;

class ChargeClubSubscription
{
    public function __construct(
        protected MollieGateway $gateway,
        protected PlatformMollieAccount $platform,
    ) {}

    /**
     * Charge one plan via its mandate. A regular charge requires an active
     * subscription and advances the billing date first; a retry re-collects an
     * overdue subscription without touching the billing date.
     */
    public function handle(ClubSubscription $subscription, bool $retry = false): ?ClubSubscriptionPayment
    {
        $expectedStatus = $retry ? SubscriptionStatus::PastDue : SubscriptionStatus::Active;

        if ($subscription->status !== $expectedStatus || $subscription->isBilledManually()) {
            return null;
        }

        $club = $subscription->club;

        if (! $club->platform_mollie_customer_id) {
            return null;
        }

        if (! $retry) {
            // Advance first so a slow webhook can never cause a double charge.
            $subscription->update([
                'next_billing_at' => ($subscription->next_billing_at ?? today())->addMonthNoOverflow(),
            ]);
        }

        $result = $this->gateway->createPayment(
            account: $this->platform,
            amountCents: $subscription->price_cents,
            description: "SquadPass {$subscription->plan->label()} — {$club->name}",
            redirectUrl: route('clubs.plan', $club),
            webhookUrl: route('webhooks.mollie.platform'),
            customerId: $club->platform_mollie_customer_id,
            sequenceType: 'recurring',
            mandateId: $subscription->mollie_mandate_id,
            metadata: [
                'purpose' => 'club_subscription_recurring',
                'club_subscription_id' => $subscription->id,
                'club_id' => $club->id,
            ],
        );

        return ClubSubscriptionPayment::create([
            'club_id' => $club->id,
            'club_subscription_id' => $subscription->id,
            'mollie_payment_id' => $result->id,
            'purpose' => ClubSubscriptionPayment::PURPOSE_RECURRING,
            'status' => PaymentStatus::Open,
            'amount_cents' => $subscription->price_cents,
            'currency' => $this->platform->mollieCurrency(),
        ]);
    }
}
