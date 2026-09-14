<?php

use App\Enums\AddonKey;
use App\Models\Addon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PRICE_CENTS = 7900;

    /**
     * Put the branded mobile app up for sale at € 79 per month. Existing
     * databases only get add-on rows once a superadmin opens the add-on
     * page, and then at € 0, so price and publish it here instead.
     */
    public function up(): void
    {
        DB::table('addons')->upsert([
            [
                'key' => AddonKey::MobileApp->value,
                'name' => AddonKey::MobileApp->defaultName(),
                'description' => AddonKey::MobileApp->defaultDescription(),
                'price_cents' => self::PRICE_CENTS,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['key'], ['price_cents', 'is_active', 'updated_at']);

        Cache::forget(Addon::LANDING_CACHE_KEY);
    }

    /**
     * Take it off sale again. The row itself only goes when no club ever
     * subscribed, so a rollback never drops paid subscriptions.
     */
    public function down(): void
    {
        $addonId = DB::table('addons')->where('key', AddonKey::MobileApp->value)->value('id');

        if ($addonId === null) {
            return;
        }

        if (DB::table('addon_subscriptions')->where('addon_id', $addonId)->exists()) {
            DB::table('addons')->where('id', $addonId)->update(['is_active' => false, 'updated_at' => now()]);
        } else {
            DB::table('addons')->where('id', $addonId)->delete();
        }

        Cache::forget(Addon::LANDING_CACHE_KEY);
    }
};
