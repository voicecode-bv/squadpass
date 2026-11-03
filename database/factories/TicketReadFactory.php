<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\TicketRead;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketRead>
 */
class TicketReadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'last_read_at' => now(),
        ];
    }
}
