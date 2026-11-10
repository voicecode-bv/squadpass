<?php

namespace App\Models;

use Database\Factories\LaunchOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The opening offer: the first clubs to take out a plan claim a founding spot
 * and keep a percentage off every plan for as long as they stay a customer.
 * A single row, managed by superadmins; read through the static helpers.
 *
 * @property int $id
 * @property bool $is_active
 * @property int $discount_percent
 * @property int $spots number of clubs that can claim the founding price
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['is_active', 'discount_percent', 'spots'])]
class LaunchOffer extends Model
{
    /** @use HasFactory<LaunchOfferFactory> */
    use HasFactory;

    public const string CACHE_KEY = 'launch-offer';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'discount_percent' => 'integer',
            'spots' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetCache());
        static::deleted(fn () => self::forgetCache());
    }

    /**
     * The offer settings, cached as a plain array and memoized per request,
     * because every marketing page reads them.
     *
     * @return array{is_active: bool, discount_percent: int, spots: int}
     */
    public static function settings(): array
    {
        return Cache::memo()->remember(self::CACHE_KEY, now()->addHour(), function (): array {
            $offer = static::query()->first();

            return [
                'is_active' => (bool) $offer?->is_active,
                'discount_percent' => (int) $offer?->discount_percent,
                'spots' => (int) $offer?->spots,
            ];
        });
    }

    public static function forgetCache(): void
    {
        Cache::memo()->forget(self::CACHE_KEY);
    }

    public static function discountPercent(): int
    {
        return self::settings()['discount_percent'];
    }

    public static function spots(): int
    {
        return self::settings()['spots'];
    }

    /**
     * Clubs that hold a founding spot.
     */
    public static function spotsTaken(): int
    {
        return Club::query()->whereNotNull('founding_claimed_at')->count();
    }

    public static function spotsLeft(): int
    {
        return max(0, self::spots() - self::spotsTaken());
    }

    /**
     * Whether a club taking out a plan now would still get the founding price.
     */
    public static function isAvailable(): bool
    {
        $settings = self::settings();

        return $settings['is_active']
            && $settings['discount_percent'] > 0
            && self::spotsLeft() > 0;
    }

    /**
     * A price with a percentage off, rounded to whole cents.
     */
    public static function applyDiscount(int $priceCents, int $discountPercent): int
    {
        return (int) round($priceCents * (100 - $discountPercent) / 100);
    }
}
