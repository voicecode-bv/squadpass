<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\AddonPayment;
use App\Models\AddonSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AddonPayment>
 */
class AddonPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'addon_subscription_id' => AddonSubscription::factory(),
            'club_id' => fn (array $attributes) => AddonSubscription::query()->findOrFail($attributes['addon_subscription_id'])->club_id,
            'mollie_payment_id' => 'tr_'.fake()->unique()->regexify('[A-Za-z0-9]{10}'),
            'purpose' => AddonPayment::PURPOSE_RECURRING,
            'status' => PaymentStatus::Open,
            'amount_cents' => 1000,
            'currency' => 'EUR',
        ];
    }

    public function paid(): static
    {
        return $this->state(['status' => PaymentStatus::Paid, 'paid_at' => now()]);
    }
}
