<?php

namespace App\Actions\ClubSubscriptions;

use App\Models\Club;
use App\Models\LaunchOffer;
use Illuminate\Support\Facades\DB;

class ClaimFoundingSpot
{
    /**
     * Give a club a founding spot in the opening offer while spots are left.
     * The offer row is locked so two clubs can never take the last spot.
     * Returns the club's founding discount, or null when it has none.
     */
    public function handle(Club $club): ?int
    {
        return DB::transaction(function () use ($club): ?int {
            $offer = LaunchOffer::query()->lockForUpdate()->first();
            $lockedClub = Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();

            if ($lockedClub->isFoundingMember()) {
                return $lockedClub->founding_discount_percent;
            }

            if (! $offer || ! $offer->is_active || $offer->discount_percent <= 0) {
                return null;
            }

            $spotsTaken = Club::query()->whereNotNull('founding_claimed_at')->count();

            if ($spotsTaken >= $offer->spots) {
                return null;
            }

            $club->forceFill([
                'founding_discount_percent' => $offer->discount_percent,
                'founding_claimed_at' => now(),
            ])->save();

            return $offer->discount_percent;
        });
    }
}
