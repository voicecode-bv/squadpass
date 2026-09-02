<?php

namespace Database\Factories;

use App\Enums\MembershipStatus;
use App\Models\Club;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'user_id' => User::factory(),
            'membership_number' => fn (array $attributes) => Club::query()
                ->whereKey($attributes['club_id'] instanceof Club ? $attributes['club_id']->id : $attributes['club_id'])
                ->firstOrFail()
                ->allocateMembershipNumber(),
            'status' => MembershipStatus::Active,
            'joined_at' => now()->toDateString(),
        ];
    }

    public function paused(): static
    {
        return $this->state(['status' => MembershipStatus::Paused]);
    }

    public function ended(): static
    {
        return $this->state([
            'status' => MembershipStatus::Ended,
            'ended_at' => now()->toDateString(),
        ]);
    }
}
