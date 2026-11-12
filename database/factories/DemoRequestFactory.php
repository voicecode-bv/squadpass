<?php

namespace Database\Factories;

use App\Enums\ClubSegment;
use App\Models\DemoRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemoRequest>
 */
class DemoRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'club_segment' => fake()->randomElement(ClubSegment::cases()),
            'other_club_type' => null,
        ];
    }

    public function otherClubType(string $type = 'Zwemschool'): static
    {
        return $this->state(fn (array $attributes) => [
            'club_segment' => null,
            'other_club_type' => $type,
        ]);
    }
}
