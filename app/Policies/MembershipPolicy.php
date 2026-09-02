<?php

namespace App\Policies;

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
}
