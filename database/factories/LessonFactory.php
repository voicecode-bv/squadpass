<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'name' => fake()->randomElement(['Squash beginners', 'Padel gevorderden', 'Conditietraining', 'Junioren training']),
            'description' => fake()->optional()->sentence(),
            'room_id' => null,
            'capacity' => fake()->numberBetween(4, 20),
            'day_of_week' => fake()->numberBetween(1, 7),
            'start_time' => fake()->randomElement(['09:00:00', '18:30:00', '20:00:00']),
            'duration_minutes' => fake()->randomElement([45, 60, 90]),
            'starts_on' => now()->toDateString(),
            'ends_on' => null,
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
