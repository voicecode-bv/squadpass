<?php

namespace App\Actions\Payments;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;

class CancelSubscription
{
    /**
     * Cancel a subscription. Access remains until the already paid period ends.
     */
    public function handle(Subscription $subscription): void
    {
        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
            'ends_at' => $subscription->next_billing_at?->subDay() ?? today(),
            'next_billing_at' => null,
        ]);
    }
}
