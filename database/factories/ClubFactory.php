<?php

namespace Database\Factories;

use App\Enums\AddonKey;
use App\Models\Addon;
use App\Models\AddonSubscription;
use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'timezone' => 'Europe/Amsterdam',
            'currency' => 'EUR',
            'self_signup' => true,
        ];
    }

    /**
     * A club that accepts online payments: Mollie key set and Mollie add-on active.
     */
    public function withMollieKey(string $key = 'test_fake_api_key'): static
    {
        return $this->state(['mollie_api_key' => $key])->withAddon(AddonKey::Mollie);
    }

    /**
     * A club with Moneybird credentials and the Moneybird add-on active.
     */
    public function withMoneybird(string $token = 'mb_token', string $administrationId = '123456'): static
    {
        return $this->state([
            'moneybird_access_token' => $token,
            'moneybird_administration_id' => $administrationId,
        ])->withAddon(AddonKey::Moneybird);
    }

    /**
     * Give the club an active subscription to the given add-on.
     */
    public function withAddon(AddonKey $key): static
    {
        return $this->afterCreating(function (Club $club) use ($key): void {
            $addon = Addon::query()->firstOrCreate(['key' => $key->value], [
                'name' => $key->defaultName(),
                'description' => $key->defaultDescription(),
                'price_cents' => 1000,
                'is_active' => true,
            ]);

            AddonSubscription::factory()->active()->create([
                'club_id' => $club->id,
                'addon_id' => $addon->id,
            ]);
        });
    }
}
