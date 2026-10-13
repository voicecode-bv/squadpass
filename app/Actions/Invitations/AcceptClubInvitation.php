<?php

namespace App\Actions\Invitations;

use App\Enums\ClubInvitationRole;
use App\Exceptions\ClubInvitationException;
use App\Exceptions\ClubPlanException;
use App\Models\ClubInvitation;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AcceptClubInvitation
{
    /**
     * Link the accepting user in the invited role. Whoever holds a valid
     * invitation link may accept it, so people can register with a
     * different email than the one that was invited.
     *
     * @throws ClubInvitationException|ClubPlanException
     */
    public function handle(ClubInvitation $invitation, User $user): void
    {
        throw_unless($invitation->isPending(), ClubInvitationException::noLongerValid());

        DB::transaction(function () use ($invitation, $user): void {
            $invitation->update(['accepted_at' => now()]);

            if ($invitation->role === ClubInvitationRole::Trainer) {
                $trainer = Trainer::query()->firstOrCreate([
                    'club_id' => $invitation->club_id,
                    'user_id' => $user->id,
                ]);

                if (! $trainer->active) {
                    $trainer->update(['active' => true]);
                }

                return;
            }

            $invitation->club->addMember($user);
        });
    }
}
