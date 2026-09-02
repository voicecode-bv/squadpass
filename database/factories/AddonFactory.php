<?php

namespace Database\Factories;

use App\Enums\AddonKey;
use App\Models\Addon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Addon>
 */
class AddonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->randomElement(AddonKey::cases());

        return [
            'key' => $key,
            'name' => $key->defaultName(),
            'description' => $key->defaultDescription(),
            'price_cents' => fake()->numberBetween(5, 50) * 100,
            'is_active' => true,
        ];
    }

    public function mollie(): static
    {
        return $this->state(['key' => AddonKey::Mollie, 'name' => AddonKey::Mollie->defaultName()]);
    }

    public function moneybird(): static
    {
        return $this->state(['key' => AddonKey::Moneybird, 'name' => AddonKey::Moneybird->defaultName()]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
