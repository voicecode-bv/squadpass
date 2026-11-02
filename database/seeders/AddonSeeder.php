<?php

namespace Database\Seeders;

use App\Enums\AddonKey;
use App\Models\Addon;
use Illuminate\Database\Seeder;

class AddonSeeder extends Seeder
{
    /**
     * Price the sellable add-ons so the superadmin page and club add-on page have data.
     * Safe to run repeatedly: add-ons are synced from AddonKey and only updated.
     */
    public function run(): void
    {
        foreach (Addon::syncFromKeys() as $addon) {
            // Exhaustive on purpose: a new add-on fails here until it has a demo price.
            $priceCents = match ($addon->key) {
                AddonKey::Mollie => 1500,
                AddonKey::Moneybird => 1000,
                AddonKey::MobileApp => 7900,
            };

            $addon->update(['price_cents' => $priceCents, 'is_active' => true]);
        }
    }
}
