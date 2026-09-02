<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Models\Club;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'name' => fake()->randomElement(['Basis', 'Onbeperkt', 'Daluren']),
            'description' => fake()->optional()->sentence(),
            'price_cents' => fake()->randomElement([2500, 3950, 5900]),
            'interval' => BillingInterval::Monthly,
            'active' => true,
        ];
    }

    public function yearly(): static
    {
        return $this->state(['interval' => BillingInterval::Yearly]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
