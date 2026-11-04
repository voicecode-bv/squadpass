<?php

namespace Database\Seeders;

use App\Enums\AddonKey;
use App\Models\Addon;
use Illuminate\Database\Seeder;

class AddonSeeder extends Seeder
{
    /**
     * Price the sellable add-ons so the superadmin page and club add-on page have data,
     * plus a Mollie + Moneybird bundle to show the bundle discount.
     * Safe to run repeatedly: add-ons are synced from AddonKey and only updated.
     */
    public function run(): void
    {
        foreach (Addon::syncFromKeys()->reject(fn (Addon $addon): bool => $addon->isBundle()) as $addon) {
            // Exhaustive on purpose: a new add-on fails here until it has a demo price.
            $priceCents = match ($addon->key) {
                AddonKey::Mollie => 1500,
                AddonKey::Moneybird => 1000,
                AddonKey::MobileApp => 7900,
                null => 0,
            };

            $addon->update(['price_cents' => $priceCents, 'is_active' => true]);
        }

        $bundle = Addon::query()->bundles()->firstOrCreate(['name' => 'Mollie + Moneybird'], [
            'description' => 'Online betalen en automatisch factureren samen, voordeliger dan los.',
            'price_cents' => 2200,
            'is_active' => true,
        ]);

        $bundle->includedAddons()->sync(Addon::query()->whereIn('key', [AddonKey::Mollie->value, AddonKey::Moneybird->value])->pluck('id'));

        Addon::forgetLandingCache();
    }
}
