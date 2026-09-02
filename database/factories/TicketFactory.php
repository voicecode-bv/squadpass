<?php

namespace Database\Factories;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Club;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'user_id' => User::factory(),
            'assigned_to' => null,
            'number' => fn (array $attributes) => Club::query()
                ->whereKey($attributes['club_id'] instanceof Club ? $attributes['club_id']->id : $attributes['club_id'])
                ->firstOrFail()
                ->allocateTicketNumber(),
            'subject' => fake()->sentence(4),
            'category' => fake()->randomElement(TicketCategory::cases()),
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Normal,
        ];
    }

    public function closed(): static
    {
        return $this->state(['status' => TicketStatus::Closed]);
    }
}
