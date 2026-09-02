<?php

namespace Database\Factories;

use App\Enums\ClubInvitationRole;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClubInvitation>
 */
class ClubInvitationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'email' => fake()->unique()->safeEmail(),
            'role' => ClubInvitationRole::Trainer,
            'token' => Str::random(64),
            'invited_by' => User::factory(),
            'expires_at' => now()->addDays(14),
        ];
    }

    public function member(): static
    {
        return $this->state(['role' => ClubInvitationRole::Member]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    public function accepted(): static
    {
        return $this->state(['accepted_at' => now()]);
    }
}
