<?php

namespace Database\Factories;

use App\Models\LaunchOffer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaunchOffer>
 */
class LaunchOfferFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'is_active' => true,
            'discount_percent' => 25,
            'spots' => 10,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
