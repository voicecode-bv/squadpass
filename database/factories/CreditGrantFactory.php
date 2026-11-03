<?php

namespace Database\Factories;

use App\Models\CreditGrant;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditGrant>
 */
class CreditGrantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory()->active(),
            'club_id' => fn (array $attributes) => Subscription::query()->whereKey($attributes['subscription_id'])->value('club_id'),
            'user_id' => fn (array $attributes) => Subscription::query()->whereKey($attributes['subscription_id'])->value('user_id'),
            'amount' => 4,
            'remaining' => fn (array $attributes) => $attributes['amount'],
            'expires_at' => now()->addDays(30),
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subMinute()]);
    }
}
