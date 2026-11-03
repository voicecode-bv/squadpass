<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Models\Club;
use App\Models\Membership;
use App\Models\User;

/**
 * Decides which club's brand colour a page wears and renders it as CSS that
 * replaces Tailwind's blue palette.
 */
class ClubBranding
{
    /**
     * The club whose colour applies: the given club, else the club in the URL,
     * else the only club the signed-in user manages or, for a member, belongs to.
     */
    public static function clubFor(?Club $club = null, ?User $user = null): ?Club
    {
        if ($club !== null) {
            return $club;
        }

        $routeClub = request()->route('club');

        if ($routeClub instanceof Club) {
            return $routeClub;
        }

        if ($user === null || $user->is_superadmin) {
            return null;
        }

        if ($user->staffClubs()->exists()) {
            return $user->soleStaffClub();
        }

        return self::memberClubFor($user);
    }

    /**
     * The club of a member's only active membership; null with none or several,
     * as no single colour then fits the member portal.
     */
    public static function memberClubFor(User $user): ?Club
    {
        $clubIds = Membership::query()
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active)
            ->limit(2)
            ->pluck('club_id');

        return $clubIds->count() === 1 ? Club::query()->whereKey($clubIds->first())->first() : null;
    }

    /**
     * CSS custom properties that repaint every blue utility (and Flux's accent,
     * which points at blue) in the club's colour; empty without a brand colour.
     */
    public static function css(?Club $club): string
    {
        $palette = $club?->brandPalette();

        if ($palette === null) {
            return '';
        }

        $properties = collect($palette)
            ->map(fn (string $hex, int $shade): string => "--color-blue-{$shade}: {$hex};")
            ->implode(' ');

        return ":root { {$properties} }";
    }
}
