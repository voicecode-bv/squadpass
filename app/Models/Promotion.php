<?php

namespace App\Models;

use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The promotion: clubs that take out a plan while it runs keep a percentage
 * off every plan for as long as they stay a customer. Customers see
 * it under its name (such as Black Friday). A single row, managed by
 * superadmins; read through the static helpers.
 *
 * @property int $id
 * @property string $name what customers see, such as "Black Friday"
 * @property bool $is_active
 * @property int $discount_percent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'is_active', 'discount_percent'])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    public const string CACHE_KEY = 'promotion';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'discount_percent' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetCache());
        static::deleted(fn () => self::forgetCache());
    }

    /**
     * The promotion settings, cached as a plain array and memoized per request,
     * because every marketing page reads them.
     *
     * @return array{name: string, is_active: bool, discount_percent: int}
     */
    public static function settings(): array
    {
        return Cache::memo()->remember(self::CACHE_KEY, now()->addHour(), function (): array {
            $promotion = static::query()->first();

            return [
                'name' => (string) $promotion?->name,
                'is_active' => (bool) $promotion?->is_active,
                'discount_percent' => (int) $promotion?->discount_percent,
            ];
        });
    }

    public static function forgetCache(): void
    {
        Cache::memo()->forget(self::CACHE_KEY);
    }

    /**
     * The promotion's name as customers see it.
     */
    public static function name(): string
    {
        return self::settings()['name'];
    }

    public static function discountPercent(): int
    {
        return self::settings()['discount_percent'];
    }

    /**
     * Clubs that took out a plan with the promotional price.
     */
    public static function claimedCount(): int
    {
        return Club::query()->whereNotNull('promotion_claimed_at')->count();
    }

    /**
     * Whether a club taking out a plan now would still get the promotional price.
     */
    public static function isAvailable(): bool
    {
        $settings = self::settings();

        return $settings['is_active'] && $settings['discount_percent'] > 0;
    }

    /**
     * A price with a percentage off, rounded to whole cents.
     */
    public static function applyDiscount(int $priceCents, int $discountPercent): int
    {
        return (int) round($priceCents * (100 - $discountPercent) / 100);
    }
}
