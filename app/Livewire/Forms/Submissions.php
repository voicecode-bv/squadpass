<?php

namespace App\Livewire\Forms;

use App\Enums\FormAudience;
use App\Enums\MembershipStatus;
use App\Models\Club;
use App\Models\Form;
use App\Models\FormAssignment;
use App\Models\FormSubmission;
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

#[Title('Form responses')]
class Submissions extends Component
{
    use WithPagination;

    public Club $club;

    public Form $form;

    #[Url]
    public string $tab = 'submitted';

    #[Url]
    public string $search = '';

    public string $assignSearch = '';

    public function mount(Club $club, Form $form): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
        $this->form = $club->forms()->with('publishedVersion')->findOrFail($form->id);
    }

    public function updatedTab(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function assign(int $userId): void
    {
        $this->authorize('manage', $this->club);

        $member = $this->clubMember($userId);

        FormAssignment::query()->firstOrCreate(
            ['form_id' => $this->form->id, 'user_id' => $member->id],
            ['assigned_by' => Auth::id()],
        );

        unset($this->assignedUserIds);
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('Form assigned to :name.', ['name' => $member->name]));
    }

    public function unassign(int $userId): void
    {
        $this->authorize('manage', $this->club);

        FormAssignment::query()
            ->where('form_id', $this->form->id)
            ->where('user_id', $userId)
            ->delete();

        unset($this->assignedUserIds);
        $this->resetPage();

        Flux::toast(variant: 'success', text: __('Assignment removed.'));
    }

    /**
     * The most recent response per member.
     *
     * @return LengthAwarePaginator<int, FormSubmission>
     */
    #[Computed]
    public function submissions(): LengthAwarePaginator
    {
        return FormSubmission::query()
            ->where('form_id', $this->form->id)
            ->where('is_current', true)
            ->with(['user', 'version'])
            ->when($this->search !== '', fn ($query) => $query->whereHas(
                'user',
                fn ($userQuery) => $userQuery->where('name', 'like', $this->search.'%')
            ))
            ->orderByDesc('submitted_at')
            ->paginate(25);
    }

    /**
     * Members who still have to fill the form in: never filled it in, filled in
     * an older version, or had their response reopened.
     *
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function pendingMembers(): LengthAwarePaginator
    {
        return User::query()
            ->whereHas('memberships', fn ($query) => $query
                ->where('club_id', $this->club->id)
                ->where('status', MembershipStatus::Active))
            ->when($this->form->audience === FormAudience::Assigned, fn ($query) => $query
                ->whereHas('formAssignments', fn ($assignment) => $assignment->where('form_id', $this->form->id)))
            ->whereDoesntHave('formSubmissions', fn ($query) => $query
                ->where('form_id', $this->form->id)
                ->where('is_current', true)
                ->whereNull('reopened_at')
                ->when($this->form->published_version_id !== null, fn ($current) => $current
                    ->where('form_version_id', $this->form->published_version_id)))
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', $this->search.'%'))
            ->orderBy('name')
            ->paginate(25);
    }

    /**
     * Members that can be assigned this form.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function assignableMembers(): Collection
    {
        return User::query()
            ->whereHas('memberships', fn ($query) => $query
                ->where('club_id', $this->club->id)
                ->where('status', MembershipStatus::Active))
            ->when($this->assignSearch !== '', fn ($query) => $query->where('name', 'like', $this->assignSearch.'%'))
            ->orderBy('name')
            ->limit(25)
            ->get();
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function assignedUserIds(): array
    {
        return FormAssignment::query()
            ->where('form_id', $this->form->id)
            ->pluck('user_id')
            ->all();
    }

    public function render(): View
    {
        return view('livewire.forms.submissions');
    }

    private function clubMember(int $userId): User
    {
        return User::query()
            ->whereHas('memberships', fn ($query) => $query->where('club_id', $this->club->id))
            ->findOrFail($userId);
    }
}
