<?php

namespace App\Models;

use App\Enums\ClubPlan;
use Database\Factories\ClubPlanSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Superadmin-managed price and member limit of a SquadPass plan.
 * Read through ClubPlan::priceCents() / memberLimit(), never directly.
 *
 * @property int $id
 * @property ClubPlan $plan
 * @property int|null $price_cents per month; null means priced on request
 * @property int|null $member_limit null means unlimited
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['plan', 'price_cents', 'member_limit'])]
class ClubPlanSetting extends Model
{
    /** @use HasFactory<ClubPlanSettingFactory> */
    use HasFactory;

    public const string CACHE_KEY = 'club-plan-settings';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => ClubPlan::class,
            'price_cents' => 'integer',
            'member_limit' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::forgetCache());
        static::deleted(fn () => self::forgetCache());
    }

    /**
     * Settings keyed by plan value, cached as a plain array and memoized per request,
     * because every member-limit check reads them.
     *
     * @return array<string, array{price_cents: int|null, member_limit: int|null}>
     */
    public static function values(): array
    {
        return Cache::memo()->remember(self::CACHE_KEY, now()->addHour(), fn (): array => static::query()
            ->get(['plan', 'price_cents', 'member_limit'])
            ->mapWithKeys(fn (self $setting): array => [$setting->plan->value => [
                'price_cents' => $setting->price_cents,
                'member_limit' => $setting->member_limit,
            ]])
            ->all());
    }

    public static function forgetCache(): void
    {
        Cache::memo()->forget(self::CACHE_KEY);
    }
}
