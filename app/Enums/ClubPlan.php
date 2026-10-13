<?php

namespace App\Enums;

use App\Models\ClubPlanSetting;

/**
 * The SquadPass plans a club pays for, tiered by the number of active members.
 * Price and limit are managed by superadmins (ClubPlanSetting); a plan without
 * a price is priced on request and activated by a superadmin.
 */
enum ClubPlan: string
{
    case Starter = 'starter';
    case Growth = 'growth';
    case Enterprise = 'enterprise';

    /**
     * Days a new club may use SquadPass, including adding members, before it needs a plan.
     */
    public const int TRIAL_DAYS = 14;

    /**
     * Active members a club may have during its trial.
     */
    public const int TRIAL_MEMBER_LIMIT = 500;

    /**
     * Plan name derived from its member limit, so it follows the superadmin settings.
     * An unlimited plan is named after the largest limit of the other plans.
     */
    public function label(): string
    {
        $limit = $this->memberLimit();

        if ($limit !== null) {
            return __('Up to :count members', ['count' => number_format($limit, 0, ',', '.')]);
        }

        $largestLimit = collect(self::cases())->map(fn (self $plan): ?int => $plan->memberLimit())->filter()->max();

        return $largestLimit === null
            ? __('Unlimited members')
            : __('More than :count members', ['count' => number_format($largestLimit, 0, ',', '.')]);
    }

    public function description(): string
    {
        return match ($this) {
            self::Starter => __('For clubs that want their member administration in order.'),
            self::Growth => __('For growing clubs with several trainers and rooms.'),
            self::Enterprise => __('A price tailored to large clubs and associations.'),
        };
    }

    /**
     * Maximum number of active members; null means unlimited.
     */
    public function memberLimit(): ?int
    {
        return $this->setting('member_limit', $this->defaultMemberLimit());
    }

    /**
     * Monthly price; null when the price is agreed per club.
     */
    public function priceCents(): ?int
    {
        return $this->setting('price_cents', $this->defaultPriceCents());
    }

    /**
     * Member limit used until a superadmin changes it (seeds club_plan_settings).
     */
    public function defaultMemberLimit(): ?int
    {
        return match ($this) {
            self::Starter => 500,
            self::Growth => 1000,
            self::Enterprise => null,
        };
    }

    /**
     * Price used until a superadmin changes it (seeds club_plan_settings).
     */
    public function defaultPriceCents(): ?int
    {
        return match ($this) {
            self::Starter => 9900,
            self::Growth => 18000,
            self::Enterprise => null,
        };
    }

    /**
     * @param  'price_cents'|'member_limit'  $key
     */
    private function setting(string $key, ?int $default): ?int
    {
        $values = ClubPlanSetting::values();

        return array_key_exists($this->value, $values) ? $values[$this->value][$key] : $default;
    }

    /**
     * Whether owners can take out this plan themselves via Mollie.
     */
    public function isSelfService(): bool
    {
        return $this->priceCents() !== null;
    }
}
