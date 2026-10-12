<?php

namespace App\Livewire\Forms;

use App\Actions\Forms\ReopenFormSubmission;
use App\Models\Club;
use App\Models\Form;
use App\Models\FormSubmission;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Form response')]
class SubmissionShow extends Component
{
    public Club $club;

    public Form $form;

    public FormSubmission $submission;

    public function mount(Club $club, Form $form, FormSubmission $submission): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
        $this->form = $club->forms()->findOrFail($form->id);
        $this->submission = FormSubmission::query()
            ->where('form_id', $this->form->id)
            ->with(['user', 'version', 'reopenedBy'])
            ->findOrFail($submission->id);
    }

    /**
     * Let the member fill this form in again. The response is kept as history.
     */
    public function reopen(ReopenFormSubmission $reopenFormSubmission): void
    {
        $this->authorize('manage', $this->club);

        abort_unless($this->submission->locks($this->form), 403);

        $reopenFormSubmission->handle($this->submission, Auth::user());

        $this->submission->refresh()->load('reopenedBy');

        Flux::toast(variant: 'success', text: __('Form reopened for :name.', ['name' => $this->submission->user->name]));
    }

    /**
     * True while the response blocks the member from filling in again.
     */
    #[Computed]
    public function locksForm(): bool
    {
        return $this->submission->locks($this->form);
    }

    /**
     * True when the member filled in an older version of the form.
     */
    #[Computed]
    public function isOutdated(): bool
    {
        return $this->form->published_version_id !== null
            && $this->form->published_version_id !== $this->submission->form_version_id;
    }

    /**
     * Earlier responses of this member, newest first.
     *
     * @return Collection<int, FormSubmission>
     */
    #[Computed]
    public function history(): Collection
    {
        return FormSubmission::query()
            ->where('form_id', $this->form->id)
            ->where('user_id', $this->submission->user_id)
            ->whereKeyNot($this->submission->id)
            ->with('version')
            ->orderByDesc('submitted_at')
            ->limit(25)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.forms.submission-show');
    }
}
