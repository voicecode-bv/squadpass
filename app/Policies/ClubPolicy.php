<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Club;
use App\Models\User;

class ClubPolicy
{
    /**
     * Any staff member may access the club's admin area.
     */
    public function view(User $user, Club $club): bool
    {
        return $user->isStaffOf($club);
    }

    /**
     * Only existing club owners may create additional clubs; regular members
     * and trainers cannot. Superadmins pass via the global Gate::before hook.
     */
    public function create(User $user): bool
    {
        return $user->ownsAnyClub();
    }

    /**
     * Only owners may change club settings (name, Mollie key, staff).
     */
    public function update(User $user, Club $club): bool
    {
        return $user->isStaffOf($club, StaffRole::Owner);
    }

    /**
     * Owners and admins handle day-to-day administration.
     */
    public function manage(User $user, Club $club): bool
    {
        return $user->isStaffOf($club);
    }
}
