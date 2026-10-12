<?php

namespace App\Livewire\Portal;

use App\Actions\Forms\StoreFormSubmission;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\FormVersion;
use App\Services\Forms\FormField;
use App\Services\Forms\FormSchema;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Form')]
class FormFill extends Component
{
    public Form $form;

    public FormVersion $version;

    public ?FormSubmission $submission = null;

    /**
     * True once the member sent the form in, until staff reopen it or the club
     * publishes a newer version.
     */
    #[Locked]
    public bool $locked = false;

    /** @var array<string, mixed> */
    public array $answers = [];

    public function mount(Form $form, StoreFormSubmission $storeFormSubmission): void
    {
        $user = Auth::user();

        abort_unless($form->isPublished() && $storeFormSubmission->isAvailableTo($form, $user), 404);

        $this->form = $form->load('club');
        $this->version = $form->publishedVersion;

        $this->submission = FormSubmission::query()
            ->where('form_id', $form->id)
            ->where('user_id', $user->id)
            ->where('is_current', true)
            ->with('version')
            ->first();

        $this->locked = (bool) $this->submission?->locks($this->form);
        $this->answers = $this->prefilledAnswers();
    }

    public function submit(StoreFormSubmission $storeFormSubmission): void
    {
        abort_if($this->locked, 403);

        $this->submission = $storeFormSubmission->handle($this->form, Auth::user(), $this->answers);

        $this->locked = $this->submission->locks($this->form);
        $this->answers = $this->prefilledAnswers();

        Flux::toast(variant: 'success', text: __('Your answers have been saved.'));
    }

    /**
     * The schema to show a sent in response with: always the version it was
     * filled in with, never the form's current version.
     */
    #[Computed]
    public function submittedSchema(): FormSchema
    {
        return $this->submission->version->schemaObject();
    }

    /**
     * @return list<FormField>
     */
    #[Computed]
    public function fields(): array
    {
        return $this->version->schemaObject()->fields;
    }

    public function render(): View
    {
        return view('livewire.portal.form-fill');
    }

    /**
     * Start from the member's previous answers where the current version
     * still has the same field; fields added since stay empty.
     *
     * @return array<string, mixed>
     */
    private function prefilledAnswers(): array
    {
        return $this->version->schemaObject()->prefill($this->submission->answers ?? []);
    }
}
