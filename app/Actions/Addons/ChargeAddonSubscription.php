<?php

namespace App\Actions\Addons;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AddonPayment;
use App\Models\AddonSubscription;
use App\Services\Mollie\MollieGateway;
use App\Services\Mollie\PlatformMollieAccount;

class ChargeAddonSubscription
{
    public function __construct(
        protected MollieGateway $gateway,
        protected PlatformMollieAccount $platform,
    ) {}

    /**
     * Charge one add-on subscription via its mandate. A regular charge requires
     * an active subscription and advances the billing date first; a retry
     * re-collects an overdue subscription without touching the billing date.
     */
    public function handle(AddonSubscription $subscription, bool $retry = false): ?AddonPayment
    {
        $expectedStatus = $retry ? SubscriptionStatus::PastDue : SubscriptionStatus::Active;

        if ($subscription->status !== $expectedStatus || ! $subscription->mollie_mandate_id) {
            return null;
        }

        $club = $subscription->club;
        $addon = $subscription->addon;

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
            description: "{$addon->name} — {$club->name}",
            redirectUrl: route('clubs.addons', $club),
            webhookUrl: route('webhooks.mollie.platform'),
            customerId: $club->platform_mollie_customer_id,
            sequenceType: 'recurring',
            mandateId: $subscription->mollie_mandate_id,
            metadata: [
                'purpose' => 'addon_recurring',
                'addon_subscription_id' => $subscription->id,
                'club_id' => $club->id,
            ],
        );

        return AddonPayment::create([
            'club_id' => $club->id,
            'addon_subscription_id' => $subscription->id,
            'mollie_payment_id' => $result->id,
            'purpose' => AddonPayment::PURPOSE_RECURRING,
            'status' => PaymentStatus::Open,
            'amount_cents' => $subscription->price_cents,
            'currency' => $this->platform->mollieCurrency(),
        ]);
    }
}
