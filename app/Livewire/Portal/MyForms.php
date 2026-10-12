<?php

namespace App\Livewire\Portal;

use App\Enums\FormAudience;
use App\Enums\MembershipStatus;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('My forms')]
class MyForms extends Component
{
    /**
     * Published forms of the clubs this member belongs to, either open to all
     * members or assigned to them.
     *
     * @return Collection<int, Form>
     */
    #[Computed]
    public function forms(): Collection
    {
        $userId = Auth::id();

        return Form::query()
            ->published()
            ->with('club')
            ->whereIn('club_id', fn ($query) => $query
                ->select('club_id')
                ->from('memberships')
                ->where('user_id', $userId)
                ->where('status', MembershipStatus::Active->value))
            ->where(fn ($query) => $query
                ->where('audience', FormAudience::AllMembers->value)
                ->orWhereHas('assignments', fn ($assignment) => $assignment->where('user_id', $userId)))
            ->orderBy('name')
            ->get();
    }

    /**
     * The member's latest response per form, keyed by form id.
     *
     * @return Collection<int, FormSubmission>
     */
    #[Computed]
    public function submissions(): Collection
    {
        return FormSubmission::query()
            ->where('user_id', Auth::id())
            ->where('is_current', true)
            ->whereIn('form_id', $this->forms->modelKeys())
            ->get()
            ->keyBy('form_id');
    }

    public function render(): View
    {
        return view('livewire.portal.my-forms');
    }
}
