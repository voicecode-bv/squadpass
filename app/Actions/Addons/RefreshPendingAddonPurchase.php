<?php

namespace App\Actions\Addons;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AddonPayment;
use App\Models\AddonSubscription;
use App\Services\Mollie\MollieGateway;
use App\Services\Mollie\PlatformMollieAccount;

class RefreshPendingAddonPurchase
{
    public function __construct(
        protected MollieGateway $gateway,
        protected PlatformMollieAccount $platform,
        protected ApplyAddonPaymentStatus $applyAddonPaymentStatus,
    ) {}

    /**
     * Check a pending add-on purchase with Mollie instead of waiting for the webhook,
     * for when the owner closed the checkout. While the first payment is still open
     * its checkout URL is returned, so they can continue paying. A cancelled or
     * expired payment drops the pending subscription, so the add-on can be bought again.
     */
    public function handle(AddonSubscription $subscription): ?string
    {
        if ($subscription->status !== SubscriptionStatus::Pending) {
            return null;
        }

        $payment = AddonPayment::query()
            ->where('addon_subscription_id', $subscription->id)
            ->where('purpose', AddonPayment::PURPOSE_FIRST)
            ->latest('id')
            ->first();

        if ($payment === null) {
            // The checkout was never created; nothing can complete this purchase.
            $subscription->delete();

            return null;
        }

        $result = $this->gateway->getPayment($this->platform, $payment->mollie_payment_id);

        if ($result->status === PaymentStatus::Open) {
            return $result->checkoutUrl;
        }

        $this->applyAddonPaymentStatus->handle($payment, $result);

        return null;
    }
}
