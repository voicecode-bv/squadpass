<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\ClubSubscription;
use App\Models\ClubSubscriptionPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubSubscriptionPayment>
 */
class ClubSubscriptionPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_subscription_id' => ClubSubscription::factory(),
            'club_id' => fn (array $attributes) => ClubSubscription::query()->findOrFail($attributes['club_subscription_id'])->club_id,
            'mollie_payment_id' => 'tr_'.fake()->unique()->regexify('[A-Za-z0-9]{10}'),
            'purpose' => ClubSubscriptionPayment::PURPOSE_RECURRING,
            'status' => PaymentStatus::Open,
            'amount_cents' => 9900,
            'currency' => 'EUR',
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
    }
}
