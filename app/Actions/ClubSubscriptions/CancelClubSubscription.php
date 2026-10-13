<?php

namespace App\Actions\ClubSubscriptions;

use App\Enums\SubscriptionStatus;
use App\Models\ClubSubscription;

class CancelClubSubscription
{
    /**
     * Cancel a plan. It stays in force until the already paid month ends;
     * nothing is charged after that.
     */
    public function handle(ClubSubscription $subscription): void
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
