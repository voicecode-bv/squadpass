<?php

namespace Database\Factories;

use App\Enums\ClubPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Club;
use App\Models\ClubSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubSubscription>
 */
class ClubSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'user_id' => User::factory(),
            'plan' => ClubPlan::Starter,
            'status' => SubscriptionStatus::Pending,
            'price_cents' => ClubPlan::Starter->priceCents(),
            'starts_at' => now()->toDateString(),
        ];
    }

    public function plan(ClubPlan $plan): static
    {
        return $this->state([
            'plan' => $plan,
            'price_cents' => $plan->priceCents() ?? 0,
        ]);
    }

    public function active(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::Active,
            'mollie_mandate_id' => 'mdt_fake',
            'next_billing_at' => now()->addMonthNoOverflow()->toDateString(),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::PastDue,
            'mollie_mandate_id' => 'mdt_fake',
            'next_billing_at' => now()->addMonthNoOverflow()->toDateString(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status' => SubscriptionStatus::Cancelled,
            'mollie_mandate_id' => 'mdt_fake',
            'cancelled_at' => now(),
            'ends_at' => now()->addDays(10)->toDateString(),
            'next_billing_at' => null,
        ]);
    }
}
