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
        $prices = [
            AddonKey::Mollie->value => 1500,
            AddonKey::Moneybird->value => 1000,
            AddonKey::MobileApp->value => 7900,
        ];

        foreach (Addon::syncFromKeys() as $addon) {
            $addon->update(['price_cents' => $prices[$addon->key->value] ?? 1000, 'is_active' => true]);
        }
    }
}
