<?php

namespace App\Livewire\Forms;

use App\Enums\FormAudience;
use App\Enums\FormStatus;
use App\Models\Club;
use App\Models\Form;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Forms')]
class Index extends Component
{
    public Club $club;

    public string $name = '';

    public string $description = '';

    public string $audience = FormAudience::AllMembers->value;

    public function mount(Club $club): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
    }

    public function createForm(): void
    {
        $this->authorize('manage', $this->club);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'audience' => ['required', Rule::enum(FormAudience::class)],
        ]);

        $form = Form::create([
            'club_id' => $this->club->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'status' => FormStatus::Draft,
            'audience' => $validated['audience'],
        ]);

        $this->redirectRoute('clubs.forms.edit', ['club' => $this->club, 'form' => $form], navigate: true);
    }

    public function archive(int $formId): void
    {
        $this->authorize('manage', $this->club);

        $form = $this->findForm($formId);

        $form->update(['status' => FormStatus::Archived]);

        unset($this->forms);

        Flux::toast(variant: 'success', text: __('Form archived.'));
    }

    /**
     * Bring an archived form back; it returns to the state its versions allow.
     */
    public function restore(int $formId): void
    {
        $this->authorize('manage', $this->club);

        $form = $this->findForm($formId);

        $form->update([
            'status' => $form->published_version_id ? FormStatus::Published : FormStatus::Draft,
        ]);

        unset($this->forms);

        Flux::toast(variant: 'success', text: __('Form restored.'));
    }

    /**
     * @return Collection<int, Form>
     */
    #[Computed]
    public function forms(): Collection
    {
        return Form::query()
            ->where('club_id', $this->club->id)
            ->with('publishedVersion')
            ->withCount([
                'submissions as current_submissions_count' => fn ($query) => $query->where('is_current', true),
            ])
            ->orderBy('name')
            ->get();
    }

    private function findForm(int $formId): Form
    {
        return Form::query()
            ->where('club_id', $this->club->id)
            ->findOrFail($formId);
    }

    public function render(): View
    {
        return view('livewire.forms.index');
    }
}
