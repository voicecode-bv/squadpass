<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Club;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'user_id' => User::factory(),
            'subscription_plan_id' => fn (array $attributes) => SubscriptionPlan::factory()->state([
                'club_id' => $attributes['club_id'],
            ]),
            'status' => SubscriptionStatus::Pending,
            'starts_at' => now()->toDateString(),
        ];
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
        ]);
    }
}
