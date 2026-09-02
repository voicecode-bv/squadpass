<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\StripCard;
use App\Models\StripCardPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StripCard>
 */
class StripCardFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'user_id' => User::factory(),
            'strip_card_plan_id' => fn (array $attributes) => StripCardPlan::factory()->state([
                'club_id' => $attributes['club_id'],
            ]),
            'strips_total' => 10,
            'strips_remaining' => 10,
            'purchased_at' => now()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
        ];
    }

    public function empty(): static
    {
        return $this->state(['strips_remaining' => 0]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()->toDateString()]);
    }
}
