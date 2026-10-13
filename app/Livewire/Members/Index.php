<?php

namespace App\Livewire\Members;

use App\Actions\Invitations\InviteToClub;
use App\Enums\ClubInvitationRole;
use App\Enums\MembershipStatus;
use App\Exceptions\ClubInvitationException;
use App\Exceptions\ClubPlanException;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\Membership;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Members')]
class Index extends Component
{
    use WithPagination;

    public Club $club;

    #[Url]
    public string $search = '';

    /** Filters the list on membership status; 'all' shows every status. */
    #[Url]
    public string $status = MembershipStatus::Active->value;

    public string $email = '';

    public function mount(Club $club): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
    }

    public function addMember(InviteToClub $inviteToClub): void
    {
        $this->authorize('manage', $this->club);

        $validated = $this->validate([
            'email' => ['required', 'email'],
        ]);

        try {
            $this->club->ensureCanAddMembers();
        } catch (ClubPlanException $exception) {
            $this->addError('email', $exception->getMessage());

            return;
        }

        $user = User::query()->where('email', $validated['email'])->first();

        // An existing account is linked directly; anyone else gets an email invitation.
        if ($user) {
            if ($user->isMemberOf($this->club)) {
                $this->addError('email', __('This person is already a member of this club.'));

                return;
            }

            try {
                $membership = $this->club->addMember($user);
            } catch (ClubPlanException $exception) {
                $this->addError('email', $exception->getMessage());

                return;
            }

            $this->reset('email');
            $this->resetPage();

            Flux::modal('add-member')->close();
            Flux::toast(variant: 'success', text: __(':name is now a member (#:number).', [
                'name' => $user->name,
                'number' => $membership->membership_number,
            ]));

            return;
        }

        try {
            $inviteToClub->handle($this->club, $validated['email'], ClubInvitationRole::Member, Auth::user());
        } catch (ClubInvitationException $exception) {
            $this->addError('email', $exception->getMessage());

            return;
        }

        $this->reset('email');
        unset($this->pendingInvitations);

        Flux::modal('add-member')->close();
        Flux::toast(variant: 'success', text: __('Invitation sent to :email.', ['email' => $validated['email']]));
    }

    public function resendInvitation(int $invitationId, InviteToClub $inviteToClub): void
    {
        $this->authorize('manage', $this->club);

        $invitation = ClubInvitation::query()
            ->where('club_id', $this->club->id)
            ->forRole(ClubInvitationRole::Member)
            ->findOrFail($invitationId);

        try {
            $this->club->ensureCanAddMembers();

            $inviteToClub->handle($this->club, $invitation->email, ClubInvitationRole::Member, Auth::user());
        } catch (ClubInvitationException|ClubPlanException $exception) {
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
            ->forRole(ClubInvitationRole::Member)
            ->findOrFail($invitationId)
            ->delete();

        unset($this->pendingInvitations);

        Flux::toast(variant: 'success', text: __('Invitation revoked.'));
    }

    public function updateStatus(int $membershipId, string $status): void
    {
        $membership = Membership::query()
            ->where('club_id', $this->club->id)
            ->findOrFail($membershipId);

        $this->authorize('update', $membership);

        $status = MembershipStatus::from($status);

        if ($status === MembershipStatus::Active) {
            // Reactivating counts towards the plan's member limit, just like adding someone new.
            try {
                $this->club->addMember($membership->user);
            } catch (ClubPlanException $exception) {
                Flux::toast(variant: 'danger', text: $exception->getMessage());

                return;
            }
        } else {
            $membership->update([
                'status' => $status,
                'ended_at' => $status === MembershipStatus::Ended ? now()->toDateString() : null,
            ]);
        }

        Flux::toast(variant: 'success', text: __('Membership updated.'));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Membership>
     */
    #[Computed]
    public function memberships(): LengthAwarePaginator
    {
        return $this->club->memberships()
            ->with('user')
            ->when(
                MembershipStatus::tryFrom($this->status),
                fn ($query, MembershipStatus $status) => $query->where('status', $status),
            )
            ->when($this->search !== '', function ($query) {
                $query->whereHas('user', function ($userQuery) {
                    $userQuery->where('name', 'like', $this->search.'%')
                        ->orWhere('email', 'like', $this->search.'%');
                });
            })
            ->orderBy('membership_number')
            ->paginate(25);
    }

    /**
     * Whether the club's trial or plan leaves room for another active member.
     */
    #[Computed]
    public function canAddMembers(): bool
    {
        return $this->club->canAddMembers();
    }

    /**
     * @return Collection<int, ClubInvitation>
     */
    #[Computed]
    public function pendingInvitations(): Collection
    {
        return ClubInvitation::query()
            ->where('club_id', $this->club->id)
            ->forRole(ClubInvitationRole::Member)
            ->whereNull('accepted_at')
            ->latest()
            ->get();
    }

    public function render(): View
    {
        return view('livewire.members.index');
    }
}
