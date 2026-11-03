<?php

namespace App\Policies;

use App\Enums\StaffPermission;
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
     * Mollie and Moneybird credentials follow the settings rule, except on a demo
     * club: there only superadmins (via the global Gate::before hook) may change them.
     */
    public function manageIntegrations(User $user, Club $club): bool
    {
        return ! $club->is_demo && $this->update($user, $club);
    }

    /**
     * Choosing a SquadPass plan or buying add-ons charges real money, so it is
     * closed on a demo club (superadmins still pass via Gate::before).
     */
    public function manageBilling(User $user, Club $club): bool
    {
        return ! $club->is_demo && $this->update($user, $club);
    }

    /**
     * Any staff member may open the club's shared pages (dashboard, settings
     * overview); each area behind them checks its own permission via access().
     */
    public function manage(User $user, Club $club): bool
    {
        return $user->staffAccessTo($club) !== null;
    }

    /**
     * Whether the user may use one area of the club, as ticked by the owner.
     * Owners may use every area.
     */
    public function access(User $user, Club $club, StaffPermission $permission): bool
    {
        return $user->hasStaffPermission($club, $permission);
    }

    /**
     * Inviting staff and setting their permissions is for owners only.
     */
    public function manageStaff(User $user, Club $club): bool
    {
        return ! $club->isDemoSandbox() && $this->update($user, $club);
    }
}
