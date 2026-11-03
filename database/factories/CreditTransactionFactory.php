<?php

namespace Database\Factories;

use App\Enums\CreditTransactionType;
use App\Models\CreditTransaction;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditTransaction>
 */
class CreditTransactionFactory extends Factory
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
            'type' => CreditTransactionType::Granted,
            'amount' => 4,
            'balance_after' => 4,
        ];
    }
}
