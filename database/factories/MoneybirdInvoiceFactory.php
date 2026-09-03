<?php

namespace Database\Factories;

use App\Enums\MoneybirdInvoiceState;
use App\Models\Club;
use App\Models\MoneybirdInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MoneybirdInvoice>
 */
class MoneybirdInvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalCents = fake()->numberBetween(500, 25000);

        return [
            'club_id' => Club::factory(),
            'moneybird_id' => (string) fake()->unique()->numberBetween(100000000, 999999999),
            'moneybird_contact_id' => (string) fake()->numberBetween(100000000, 999999999),
            'invoice_number' => '2026-'.Str::padLeft((string) fake()->unique()->numberBetween(1, 9999), 4, '0'),
            'state' => MoneybirdInvoiceState::Open,
            'invoice_date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'total_price_incl_tax_cents' => $totalCents,
            'currency' => 'EUR',
            'is_credit_invoice' => false,
            'lines' => [[
                'description' => fake()->words(3, true),
                'amount' => '1',
                'price_cents' => $totalCents,
                'total_price_incl_tax_cents' => $totalCents,
            ]],
            'moneybird_updated_at' => now(),
            'synced_at' => now(),
        ];
    }

    public function creditInvoice(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_credit_invoice' => true,
            'total_price_incl_tax_cents' => -abs($attributes['total_price_incl_tax_cents']),
        ]);
    }

    public function paid(): static
    {
        return $this->state(['state' => MoneybirdInvoiceState::Paid]);
    }
}
