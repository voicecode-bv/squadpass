<?php

namespace Database\Factories;

use App\Enums\MemberImportStatus;
use App\Models\Club;
use App\Models\MemberImport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberImport>
 */
class MemberImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'path' => 'member-imports/'.fake()->uuid().'.csv',
            'original_name' => 'leden.csv',
            'column_map' => ['name' => 0, 'email' => 1],
            'send_invitations' => false,
            'status' => MemberImportStatus::Pending,
        ];
    }

    public function completed(): static
    {
        return $this->state(['status' => MemberImportStatus::Completed, 'finished_at' => now()]);
    }
}
