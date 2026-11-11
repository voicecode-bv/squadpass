<?php

namespace App\Actions\ClubSubscriptions;

use App\Models\Club;
use App\Models\Promotion;
use Illuminate\Support\Facades\DB;

class ClaimPromotion
{
    /**
     * Give a club the promotional price while the promotion runs. A club keeps
     * the discount it claimed, also after the promotion changes or ends.
     * Returns the club's promotional discount, or null when it has none.
     */
    public function handle(Club $club): ?int
    {
        return DB::transaction(function () use ($club): ?int {
            $promotion = Promotion::query()->first();
            $lockedClub = Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();

            if ($lockedClub->hasPromotionalPrice()) {
                return $lockedClub->promotion_discount_percent;
            }

            if (! $promotion || ! $promotion->is_active || $promotion->discount_percent <= 0) {
                return null;
            }

            $club->forceFill([
                'promotion_discount_percent' => $promotion->discount_percent,
                'promotion_claimed_at' => now(),
            ])->save();

            return $promotion->discount_percent;
        });
    }
}
