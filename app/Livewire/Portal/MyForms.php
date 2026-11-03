<?php

namespace App\Livewire\Portal;

use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Computed properties, read as $this->name:
 *
 * @property-read Collection<int, Form> $forms
 */
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
        return Form::query()
            ->forMember(Auth::user()->id)
            ->with('club')
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
