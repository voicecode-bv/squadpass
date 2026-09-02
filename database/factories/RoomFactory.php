<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'name' => fake()->unique()->randomElement(['Baan 1', 'Baan 2', 'Baan 3', 'Zaal A', 'Zaal B', 'Studio']).' '.fake()->unique()->numberBetween(1, 999),
            'description' => fake()->optional()->sentence(),
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
