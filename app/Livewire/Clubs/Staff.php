<?php

namespace App\Livewire\Clubs;

use App\Actions\Invitations\InviteToClub;
use App\Enums\ClubInvitationRole;
use App\Enums\StaffPermission;
use App\Enums\StaffRole;
use App\Exceptions\ClubInvitationException;
use App\Models\Club;
use App\Models\ClubInvitation;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Owners invite colleagues and tick per person which areas of the club they may use.
 *
 * @property-read Collection<int, User> $staffMembers
 * @property-read Collection<int, ClubInvitation> $pendingInvitations
 */
#[Title('Staff')]
class Staff extends Component
{
    public Club $club;

    public string $email = '';

    /**
     * Ticked permission values in the invite or edit modal.
     *
     * @var list<string>
     */
    public array $selectedPermissions = [];

    /** Staff member being edited; null while inviting someone new. */
    public ?int $editingUserId = null;

    public function mount(Club $club): void
    {
        $this->authorize('manageStaff', $club);

        $this->club = $club;
    }

    public function startInvite(): void
    {
        $this->authorize('manageStaff', $this->club);

        $this->resetForm();
        $this->applyPreset('desk');

        Flux::modal('staff-member')->show();
    }

    /**
     * Tick the boxes of a preset; the owner can still adjust them.
     */
    public function applyPreset(string $preset): void
    {
        $permissions = StaffPermission::presets()[$preset]['permissions'] ?? null;

        if ($permissions !== null) {
            $this->selectedPermissions = array_map(fn (StaffPermission $permission): string => $permission->value, $permissions);
        }
    }

    public function editStaff(int $userId): void
    {
        $this->authorize('manageStaff', $this->club);

        $member = $this->findStaffMember($userId);

        $this->resetForm();
        $this->editingUserId = $member->id;
        $this->selectedPermissions = array_map(
            fn (StaffPermission $permission): string => $permission->value,
            $member->staffAccessTo($this->club)['permissions'] ?? [],
        );

        Flux::modal('staff-member')->show();
    }

    public function save(InviteToClub $inviteToClub): void
    {
        $this->authorize('manageStaff', $this->club);

        $validated = $this->validate([
            'email' => [Rule::requiredIf($this->editingUserId === null), 'nullable', 'email'],
            'selectedPermissions' => ['required', 'array', 'min:1'],
            'selectedPermissions.*' => [Rule::enum(StaffPermission::class)],
        ], [
            'selectedPermissions.required' => __('Choose at least one area.'),
            'selectedPermissions.min' => __('Choose at least one area.'),
        ]);

        $permissions = StaffPermission::fromValues($validated['selectedPermissions']);

        if ($this->editingUserId !== null) {
            $this->club->addStaff($this->findStaffMember($this->editingUserId), StaffRole::Admin, $permissions);

            $this->finish(__('Permissions saved.'));

            return;
        }

        try {
            $inviteToClub->handle($this->club, $validated['email'], ClubInvitationRole::Staff, Auth::user(), $permissions);
        } catch (ClubInvitationException $exception) {
            $this->addError('email', $exception->getMessage());

            return;
        }

        $this->finish(__('Invitation sent to :email.', ['email' => $validated['email']]));
    }

    public function removeStaff(int $userId): void
    {
        $this->authorize('manageStaff', $this->club);

        $this->club->staff()->detach($this->findStaffMember($userId)->id);

        unset($this->staffMembers);

        Flux::toast(variant: 'success', text: __('Staff member removed. They no longer have access to this club.'));
    }

    public function resendInvitation(int $invitationId, InviteToClub $inviteToClub): void
    {
        $this->authorize('manageStaff', $this->club);

        $invitation = $this->findInvitation($invitationId);

        try {
            $inviteToClub->handle($this->club, $invitation->email, ClubInvitationRole::Staff, Auth::user(), StaffPermission::fromValues($invitation->permissions));
        } catch (ClubInvitationException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->pendingInvitations);

        Flux::toast(variant: 'success', text: __('Invitation sent to :email.', ['email' => $invitation->email]));
    }

    public function revokeInvitation(int $invitationId): void
    {
        $this->authorize('manageStaff', $this->club);

        $this->findInvitation($invitationId)->delete();

        unset($this->pendingInvitations);

        Flux::toast(variant: 'success', text: __('Invitation revoked.'));
    }

    /**
     * Owners first, then staff members by name.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function staffMembers(): Collection
    {
        return $this->club->staff()
            ->orderByRaw('CASE WHEN club_staff.role = ? THEN 0 ELSE 1 END', [StaffRole::Owner->value])
            ->orderBy('users.name')
            ->get();
    }

    /**
     * @return Collection<int, ClubInvitation>
     */
    #[Computed]
    public function pendingInvitations(): Collection
    {
        return ClubInvitation::query()
            ->where('club_id', $this->club->id)
            ->forRole(ClubInvitationRole::Staff)
            ->whereNull('accepted_at')
            ->latest()
            ->get();
    }

    public function render(): View
    {
        return view('livewire.clubs.staff');
    }

    /**
     * Only staff members can be edited or removed here, never an owner.
     */
    protected function findStaffMember(int $userId): User
    {
        return $this->club->staff()
            ->wherePivot('role', StaffRole::Admin->value)
            ->whereKey($userId)
            ->firstOrFail();
    }

    protected function findInvitation(int $invitationId): ClubInvitation
    {
        return ClubInvitation::query()
            ->where('club_id', $this->club->id)
            ->forRole(ClubInvitationRole::Staff)
            ->findOrFail($invitationId);
    }

    protected function resetForm(): void
    {
        $this->reset('email', 'selectedPermissions', 'editingUserId');
        $this->resetValidation();
    }

    protected function finish(string $message): void
    {
        $this->resetForm();
        unset($this->staffMembers, $this->pendingInvitations);

        Flux::modal('staff-member')->close();
        Flux::toast(variant: 'success', text: $message);
    }
}
