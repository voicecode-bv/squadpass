<?php

namespace App\Actions\Addons;

use App\Enums\AddonKey;
use App\Enums\SubscriptionStatus;
use App\Models\Addon;
use App\Models\AddonSubscription;
use App\Models\Club;
use Illuminate\Support\Collection;

/**
 * Works out what happens to a club's current add-ons when it buys another one,
 * typically a bundle: which subscriptions it replaces, and how much of their
 * paid month is still unused and deducted from the first payment.
 */
class CalculateAddonUpgrade
{
    /**
     * The lowest first payment Mollie accepts; it also establishes the mandate.
     */
    public const int MINIMUM_FIRST_PAYMENT_CENTS = 1;

    /**
     * Subscriptions that become redundant once the club has the given add-on:
     * every feature they unlock is unlocked by the new add-on as well.
     *
     * @return Collection<int, AddonSubscription>
     */
    public function replacedSubscriptions(Club $club, Addon $addon): Collection
    {
        return $this->relevantSubscriptions($club, $addon)
            ->filter(fn (AddonSubscription $subscription): bool => $addon->covers($subscription->addon))
            ->values();
    }

    /**
     * Whether the club already has every feature of the add-on through
     * subscriptions it would keep, so buying it adds nothing.
     */
    public function addsNothing(Club $club, Addon $addon): bool
    {
        $keptKeys = $this->relevantSubscriptions($club, $addon)
            ->filter(fn (AddonSubscription $subscription): bool => $subscription->isCurrent() && ! $addon->covers($subscription->addon))
            ->flatMap(fn (AddonSubscription $subscription): array => $subscription->addon->unlockedKeys())
            ->map(fn (AddonKey $key): string => $key->value);

        $keys = array_map(fn (AddonKey $key): string => $key->value, $addon->unlockedKeys());

        return $keys !== [] && array_diff($keys, $keptKeys->all()) === [];
    }

    /**
     * The unused value of the replaced subscriptions, deducted from the first
     * payment. Capped so the first payment never drops below the Mollie minimum.
     */
    public function creditCents(Club $club, Addon $addon): int
    {
        $credit = $this->replacedSubscriptions($club, $addon)
            ->sum(fn (AddonSubscription $subscription): int => $this->unusedValueCents($subscription));

        return (int) max(0, min($credit, $addon->price_cents - self::MINIMUM_FIRST_PAYMENT_CENTS));
    }

    /**
     * The share of the current paid month that is still to come, today included,
     * since the replacing add-on takes over from today.
     */
    public function unusedValueCents(AddonSubscription $subscription): int
    {
        $periodEnd = match (true) {
            $subscription->status === SubscriptionStatus::Active => $subscription->next_billing_at,
            $subscription->status === SubscriptionStatus::Cancelled && $subscription->isCurrent() => $subscription->ends_at->copy()->addDay(),
            default => null,
        };

        if ($periodEnd === null || $periodEnd->lte(today())) {
            return 0;
        }

        $periodStart = $periodEnd->copy()->subMonthNoOverflow();
        $periodDays = (int) $periodStart->diffInDays($periodEnd);
        $remainingDays = min((int) today()->diffInDays($periodEnd), $periodDays);

        return (int) round($subscription->price_cents * $remainingDays / $periodDays);
    }

    /**
     * The club's other subscriptions that are running or still being collected.
     *
     * @return Collection<int, AddonSubscription>
     */
    protected function relevantSubscriptions(Club $club, Addon $addon): Collection
    {
        return AddonSubscription::query()
            ->where('club_id', $club->id)
            ->where('addon_id', '!=', $addon->id)
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue, SubscriptionStatus::Cancelled])
            ->with('addon.includedAddons')
            ->get()
            ->filter(fn (AddonSubscription $subscription): bool => $subscription->status !== SubscriptionStatus::Cancelled || $subscription->isCurrent())
            ->values();
    }
}
