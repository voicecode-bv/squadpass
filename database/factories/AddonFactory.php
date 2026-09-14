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
        $key = fake()->randomElement($this->unusedKeys());

        return [
            'key' => $key,
            'name' => $key->defaultName(),
            'description' => $key->defaultDescription(),
            'price_cents' => fake()->numberBetween(5, 50) * 100,
            'is_active' => true,
        ];
    }

    /**
     * Keys without a row yet. The migration seeds the mobile app add-on and
     * addons.key is unique, so picking blindly would clash.
     *
     * @return list<AddonKey>
     */
    protected function unusedKeys(): array
    {
        $taken = Addon::query()->pluck('key')->map(fn (AddonKey $key) => $key->value)->all();

        $available = array_values(array_filter(
            AddonKey::cases(),
            fn (AddonKey $key) => ! in_array($key->value, $taken, true),
        ));

        return $available === [] ? AddonKey::cases() : $available;
    }

    public function mollie(): static
    {
        return $this->state(['key' => AddonKey::Mollie, 'name' => AddonKey::Mollie->defaultName()]);
    }

    public function moneybird(): static
    {
        return $this->state(['key' => AddonKey::Moneybird, 'name' => AddonKey::Moneybird->defaultName()]);
    }

    public function mobileApp(): static
    {
        return $this->state(['key' => AddonKey::MobileApp, 'name' => AddonKey::MobileApp->defaultName()]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
