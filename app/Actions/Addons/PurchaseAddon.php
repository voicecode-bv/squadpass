<?php

namespace App\Actions\Addons;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\PaymentException;
use App\Models\Addon;
use App\Models\AddonPayment;
use App\Models\AddonSubscription;
use App\Models\Club;
use App\Models\User;
use App\Services\Mollie\MollieGateway;
use App\Services\Mollie\PlatformMollieAccount;

class PurchaseAddon
{
    public function __construct(
        protected MollieGateway $gateway,
        protected PlatformMollieAccount $platform,
        protected ResolvePlatformMollieCustomer $resolveCustomer,
    ) {}

    /**
     * Start an add-on subscription for a club. The first payment on the platform
     * Mollie account establishes the mandate and covers the first month.
     * Returns the checkout URL.
     */
    public function handle(Club $club, Addon $addon, User $purchaser): string
    {
        throw_unless($addon->is_active, PaymentException::addonUnavailable());
        throw_unless($this->platform->isConfigured(), PaymentException::platformPaymentsUnavailable());

        $blocking = AddonSubscription::query()
            ->where('club_id', $club->id)
            ->where('addon_id', $addon->id)
            ->whereIn('status', [SubscriptionStatus::Pending, SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::Cancelled])
            ->get()
            ->first(fn (AddonSubscription $subscription) => $subscription->blocksNewSubscription());

        throw_if($blocking, PaymentException::addonAlreadyActive());

        $customerId = $this->resolveCustomer->handle($club, $purchaser->email);

        $subscription = AddonSubscription::create([
            'club_id' => $club->id,
            'addon_id' => $addon->id,
            'user_id' => $purchaser->id,
            'status' => SubscriptionStatus::Pending,
            'price_cents' => $addon->price_cents,
            'starts_at' => today(),
        ]);

        $result = $this->gateway->createPayment(
            account: $this->platform,
            amountCents: $addon->price_cents,
            description: "{$addon->name} — {$club->name}",
            redirectUrl: route('clubs.addons', $club),
            webhookUrl: route('webhooks.mollie.platform'),
            customerId: $customerId,
            sequenceType: 'first',
            metadata: [
                'purpose' => 'addon_first',
                'addon_subscription_id' => $subscription->id,
                'club_id' => $club->id,
            ],
        );

        AddonPayment::create([
            'club_id' => $club->id,
            'addon_subscription_id' => $subscription->id,
            'mollie_payment_id' => $result->id,
            'purpose' => AddonPayment::PURPOSE_FIRST,
            'status' => PaymentStatus::Open,
            'amount_cents' => $addon->price_cents,
            'currency' => $this->platform->mollieCurrency(),
        ]);

        return $result->checkoutUrl ?? route('clubs.addons', $club);
    }
}
