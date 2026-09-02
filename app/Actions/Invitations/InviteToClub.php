<?php

namespace App\Actions\Invitations;

use App\Enums\ClubInvitationRole;
use App\Exceptions\ClubInvitationException;
use App\Mail\ClubInvitationMail;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InviteToClub
{
    /**
     * Invite someone by email to join the club as a trainer or member. Works
     * for people without an account (they register first) and existing users.
     * Re-inviting refreshes the token and expiry and resends the email.
     */
    public function handle(Club $club, string $email, ClubInvitationRole $role, User $invitedBy): ClubInvitation
    {
        $existingUser = User::query()->where('email', $email)->first();

        if ($existingUser) {
            $this->ensureNotAlreadyInRole($club, $existingUser, $role);
        }

        $invitation = ClubInvitation::query()->updateOrCreate(
            ['club_id' => $club->id, 'email' => $email, 'role' => $role],
            [
                'token' => Str::random(64),
                'invited_by' => $invitedBy->id,
                'accepted_at' => null,
                'expires_at' => now()->addDays(14),
            ],
        );

        Mail::to($email)->send(new ClubInvitationMail($invitation));

        return $invitation;
    }

    protected function ensureNotAlreadyInRole(Club $club, User $user, ClubInvitationRole $role): void
    {
        if ($role === ClubInvitationRole::Trainer) {
            $alreadyTrainer = Trainer::query()
                ->where('club_id', $club->id)
                ->where('user_id', $user->id)
                ->exists();

            throw_if($alreadyTrainer, ClubInvitationException::alreadyTrainer());

            return;
        }

        throw_if($user->isMemberOf($club), ClubInvitationException::alreadyMember());
    }
}
