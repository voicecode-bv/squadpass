<?php

namespace App\Actions\Addons;

use App\Enums\SubscriptionStatus;
use App\Models\AddonSubscription;

class CancelAddonSubscription
{
    /**
     * Cancel an add-on subscription. The add-on stays unlocked until the
     * already paid month ends; nothing is charged after that.
     */
    public function handle(AddonSubscription $subscription): void
    {
        if (! in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)) {
            return;
        }

        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'ends_at' => $subscription->status === SubscriptionStatus::Active
                ? ($subscription->next_billing_at?->subDay() ?? today())
                : today()->subDay(),
            'next_billing_at' => null,
        ]);
    }
}
