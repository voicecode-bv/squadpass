<?php

namespace App\Livewire\Trainers;

use App\Actions\Invitations\InviteToClub;
use App\Enums\ClubInvitationRole;
use App\Exceptions\ClubInvitationException;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\Trainer;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Trainers')]
class Index extends Component
{
    public Club $club;

    public string $email = '';

    public function mount(Club $club): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
    }

    public function inviteTrainer(InviteToClub $inviteToClub): void
    {
        $this->authorize('manage', $this->club);

        $validated = $this->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $inviteToClub->handle($this->club, $validated['email'], ClubInvitationRole::Trainer, Auth::user());
        } catch (ClubInvitationException $exception) {
            $this->addError('email', $exception->getMessage());

            return;
        }

        $this->reset('email');
        unset($this->pendingInvitations);

        Flux::modal('invite-trainer')->close();
        Flux::toast(variant: 'success', text: __('Invitation sent to :email.', ['email' => $validated['email']]));
    }

    public function resendInvitation(int $invitationId, InviteToClub $inviteToClub): void
    {
        $this->authorize('manage', $this->club);

        $invitation = ClubInvitation::query()
            ->where('club_id', $this->club->id)
            ->forRole(ClubInvitationRole::Trainer)
            ->findOrFail($invitationId);

        try {
            $inviteToClub->handle($this->club, $invitation->email, ClubInvitationRole::Trainer, Auth::user());
        } catch (ClubInvitationException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->pendingInvitations);

        Flux::toast(variant: 'success', text: __('Invitation sent to :email.', ['email' => $invitation->email]));
    }

    public function revokeInvitation(int $invitationId): void
    {
        $this->authorize('manage', $this->club);

        ClubInvitation::query()
            ->where('club_id', $this->club->id)
            ->forRole(ClubInvitationRole::Trainer)
            ->findOrFail($invitationId)
            ->delete();

        unset($this->pendingInvitations);

        Flux::toast(variant: 'success', text: __('Invitation revoked.'));
    }

    public function toggleActive(int $trainerId): void
    {
        $this->authorize('manage', $this->club);

        $trainer = Trainer::query()
            ->where('club_id', $this->club->id)
            ->findOrFail($trainerId);

        $trainer->update(['active' => ! $trainer->active]);
    }

    /**
     * @return Collection<int, Trainer>
     */
    #[Computed]
    public function trainers(): Collection
    {
        return Trainer::query()
            ->where('club_id', $this->club->id)
            ->with('user')
            ->withCount('lessons')
            ->get()
            ->sortBy(fn (Trainer $trainer) => $trainer->user->name)
            ->values();
    }

    /**
     * @return Collection<int, ClubInvitation>
     */
    #[Computed]
    public function pendingInvitations(): Collection
    {
        return ClubInvitation::query()
            ->where('club_id', $this->club->id)
            ->forRole(ClubInvitationRole::Trainer)
            ->whereNull('accepted_at')
            ->latest()
            ->get();
    }

    public function render(): View
    {
        return view('livewire.trainers.index');
    }
}
