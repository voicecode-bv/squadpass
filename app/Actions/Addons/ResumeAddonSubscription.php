<?php

namespace App\Actions\Addons;

use App\Enums\SubscriptionStatus;
use App\Models\AddonSubscription;

class ResumeAddonSubscription
{
    /**
     * Undo a cancellation while the paid period is still running: billing
     * simply continues from the day after the current period ends.
     */
    public function handle(AddonSubscription $subscription): bool
    {
        if ($subscription->status !== SubscriptionStatus::Cancelled || ! $subscription->isCurrent() || ! $subscription->mollie_mandate_id) {
            return false;
        }

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'cancelled_at' => null,
            'next_billing_at' => $subscription->ends_at->addDay(),
            'ends_at' => null,
        ]);

        return true;
    }
}
