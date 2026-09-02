<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Club;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory(),
            'user_id' => User::factory(),
            'mollie_payment_id' => 'tr_'.Str::random(10),
            'status' => PaymentStatus::Open,
            'amount_cents' => fake()->numberBetween(500, 10000),
            'currency' => 'EUR',
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
