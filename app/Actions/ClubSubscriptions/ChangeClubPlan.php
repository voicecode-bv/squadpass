<?php

namespace App\Actions\ClubSubscriptions;

use App\Enums\ClubPlan;
use App\Enums\SubscriptionStatus;
use App\Exceptions\ClubPlanException;
use App\Models\ClubSubscription;

class ChangeClubPlan
{
    /**
     * Switch an active, self-service plan to another tier. The new member limit
     * applies right away; the new price from the next billing date, so there is
     * nothing to prorate.
     *
     * @throws ClubPlanException
     */
    public function handle(ClubSubscription $subscription, ClubPlan $plan): void
    {
        throw_unless($plan->isSelfService(), ClubPlanException::notSelfService());

        if ($subscription->status !== SubscriptionStatus::Active || $subscription->isBilledManually() || $subscription->plan === $plan) {
            return;
        }

        $limit = $plan->memberLimit();

        throw_if($limit !== null && $subscription->club->activeMemberCount() > $limit, ClubPlanException::tooManyMembersForPlan($limit ?? 0));

        $subscription->update([
            'plan' => $plan,
            'price_cents' => $plan->priceCents(),
        ]);
    }
}
