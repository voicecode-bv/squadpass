<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\StripCardPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StripCardPlan>
 */
class StripCardPlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $strips = fake()->randomElement([5, 10, 20]);

        return [
            'club_id' => Club::factory(),
            'name' => "{$strips}-strippenkaart",
            'strips' => $strips,
            'price_cents' => $strips * 750,
            'validity_months' => 12,
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
