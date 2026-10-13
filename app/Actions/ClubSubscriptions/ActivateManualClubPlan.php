<?php

namespace App\Actions\ClubSubscriptions;

use App\Enums\ClubPlan;
use App\Enums\SubscriptionStatus;
use App\Exceptions\ClubPlanException;
use App\Models\Club;
use App\Models\ClubSubscription;
use App\Models\User;

class ActivateManualClubPlan
{
    /**
     * Activate a plan that is invoiced outside SquadPass (typically Enterprise).
     * Without a mandate the charge command skips it; it runs until cancelled.
     *
     * @throws ClubPlanException
     */
    public function handle(Club $club, ClubPlan $plan, User $activatedBy): ClubSubscription
    {
        $blocking = ClubSubscription::query()
            ->where('club_id', $club->id)
            ->whereIn('status', [SubscriptionStatus::Pending, SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::Cancelled])
            ->get()
            ->first(fn (ClubSubscription $subscription) => $subscription->blocksNewSubscription());

        throw_if($blocking, ClubPlanException::alreadySubscribed());

        return ClubSubscription::create([
            'club_id' => $club->id,
            'user_id' => $activatedBy->id,
            'plan' => $plan,
            'status' => SubscriptionStatus::Active,
            'price_cents' => 0,
            'starts_at' => today(),
        ]);
    }
}
