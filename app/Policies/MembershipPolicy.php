<?php

namespace App\Policies;

use App\Enums\StaffRole;
use App\Models\Membership;
use App\Models\User;

class MembershipPolicy
{
    public function view(User $user, Membership $membership): bool
    {
        return $user->isStaffOf($membership->club) || $user->id === $membership->user_id;
    }

    public function update(User $user, Membership $membership): bool
    {
        return $user->isStaffOf($membership->club);
    }

    public function delete(User $user, Membership $membership): bool
    {
        return $user->isStaffOf($membership->club);
    }

    /**
     * Only club owners may browse the app as one of their members. The
     * controller additionally refuses targets that outrank the member.
     */
    public function impersonate(User $user, Membership $membership): bool
    {
        return $user->isStaffOf($membership->club, StaffRole::Owner);
    }
}
