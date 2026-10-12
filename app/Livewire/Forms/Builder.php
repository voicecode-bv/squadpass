<?php

namespace App\Livewire\Forms;

use App\Actions\Forms\PublishFormVersion;
use App\Actions\Forms\StartFormDraft;
use App\Enums\FormAudience;
use App\Enums\FormFieldType;
use App\Exceptions\FormException;
use App\Models\Club;
use App\Models\Form;
use App\Models\FormVersion;
use App\Services\Forms\FormField;
use App\Services\Forms\FormSchema;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Edit form')]
class Builder extends Component
{
    public Club $club;

    public Form $form;

    public FormVersion $draft;

    public string $name = '';

    public string $description = '';

    public string $audience = FormAudience::AllMembers->value;

    /** Key of the field being edited, empty while adding a new one. */
    public string $editingKey = '';

    public string $fieldType = 'text';

    public string $fieldLabel = '';

    public string $fieldHelp = '';

    public bool $fieldRequired = false;

    /** @var array<int, array{value: string, label: string}> */
    public array $fieldOptions = [];

    public function mount(Club $club, Form $form, StartFormDraft $startFormDraft): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
        $this->form = $club->forms()->findOrFail($form->id);
        $this->draft = $startFormDraft->handle($this->form);

        $this->name = $this->form->name;
        $this->description = (string) $this->form->description;
        $this->audience = $this->form->audience->value;
    }

    public function saveSettings(): void
    {
        $this->authorize('manage', $this->club);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'audience' => ['required', Rule::enum(FormAudience::class)],
        ]);

        $this->form->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
            'audience' => $validated['audience'],
        ]);

        Flux::toast(variant: 'success', text: __('Form updated.'));
    }

    public function newField(string $type): void
    {
        $this->authorize('manage', $this->club);

        $this->resetFieldEditor();
        $this->fieldType = FormFieldType::from($type)->value;

        if (FormFieldType::from($type)->hasOptions()) {
            $this->fieldOptions = [$this->blankOption(), $this->blankOption()];
        }

        Flux::modal('field-editor')->show();
    }

    public function editField(string $key): void
    {
        $this->authorize('manage', $this->club);

        $field = $this->schema()->field($key);

        if (! $field) {
            return;
        }

        $this->resetValidation();

        $this->editingKey = $field->key;
        $this->fieldType = $field->type->value;
        $this->fieldLabel = $field->label;
        $this->fieldHelp = (string) $field->help;
        $this->fieldRequired = $field->required;
        $this->fieldOptions = $field->options;

        Flux::modal('field-editor')->show();
    }

    public function addOption(): void
    {
        $this->fieldOptions[] = $this->blankOption();
    }

    public function removeOption(int $index): void
    {
        unset($this->fieldOptions[$index]);

        $this->fieldOptions = array_values($this->fieldOptions);
    }

    public function saveField(): void
    {
        $this->authorize('manage', $this->club);

        $type = FormFieldType::from($this->fieldType);

        $this->validate([
            'fieldLabel' => ['required', 'string', 'max:255'],
            'fieldHelp' => ['nullable', 'string', 'max:2000'],
            'fieldOptions' => [$type->hasOptions() ? 'required' : 'nullable', 'array', 'max:100'],
            'fieldOptions.*.label' => [$type->hasOptions() ? 'required' : 'nullable', 'string', 'max:255'],
        ], attributes: [
            'fieldLabel' => __('Label'),
            'fieldHelp' => __('Help text'),
            'fieldOptions' => __('Options'),
            'fieldOptions.*.label' => __('Option'),
        ]);

        // Existing fields keep their key, so answers given earlier stay linked.
        $field = new FormField(
            key: $this->editingKey !== '' ? $this->editingKey : FormField::newKey(),
            type: $type,
            label: $this->fieldLabel,
            help: $this->fieldHelp !== '' ? $this->fieldHelp : null,
            required: $type === FormFieldType::Heading ? false : $this->fieldRequired,
            options: $type->hasOptions() ? $this->normalizedOptions() : [],
        );

        $fields = $this->schema()->fields;

        if ($this->editingKey !== '') {
            $fields = array_map(fn (FormField $existing): FormField => $existing->key === $field->key ? $field : $existing, $fields);
        } else {
            $fields[] = $field;
        }

        $this->persist(new FormSchema($fields));
        $this->resetFieldEditor();

        Flux::modal('field-editor')->close();
    }

    public function deleteField(string $key): void
    {
        $this->authorize('manage', $this->club);

        $fields = array_values(array_filter(
            $this->schema()->fields,
            fn (FormField $field): bool => $field->key !== $key,
        ));

        $this->persist(new FormSchema($fields));
    }

    public function moveField(string $key, int $direction): void
    {
        $this->authorize('manage', $this->club);

        $fields = $this->schema()->fields;
        $index = null;

        foreach ($fields as $position => $field) {
            if ($field->key === $key) {
                $index = $position;
                break;
            }
        }

        $target = $index === null ? null : $index + $direction;

        if ($index === null || $target === null || $target < 0 || $target >= count($fields)) {
            return;
        }

        [$fields[$index], $fields[$target]] = [$fields[$target], $fields[$index]];

        $this->persist(new FormSchema(array_values($fields)));
    }

    public function publish(PublishFormVersion $publishFormVersion): void
    {
        $this->authorize('manage', $this->club);

        try {
            $publishFormVersion->handle($this->form, $this->draft);
        } catch (FormException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->redirectRoute('clubs.forms', ['club' => $this->club], navigate: true);
    }

    /**
     * @return list<FormField>
     */
    #[Computed]
    public function fields(): array
    {
        return $this->schema()->fields;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function fieldTypes(): array
    {
        $types = [];

        foreach (FormFieldType::cases() as $type) {
            $types[$type->value] = $type->label();
        }

        return $types;
    }

    public function render(): View
    {
        return view('livewire.forms.builder');
    }

    private function schema(): FormSchema
    {
        return $this->draft->schemaObject();
    }

    private function persist(FormSchema $schema): void
    {
        $this->draft->setSchema($schema);
        $this->draft->save();

        unset($this->fields);
    }

    /**
     * Option values are generated once and kept, so relabelling an option
     * does not change what earlier answers mean.
     *
     * @return list<array{value: string, label: string}>
     */
    private function normalizedOptions(): array
    {
        return array_values(array_map(fn (array $option): array => [
            'value' => $option['value'] !== '' ? $option['value'] : (string) Str::ulid(),
            'label' => $option['label'],
        ], $this->fieldOptions));
    }

    /**
     * @return array{value: string, label: string}
     */
    private function blankOption(): array
    {
        return ['value' => (string) Str::ulid(), 'label' => ''];
    }

    private function resetFieldEditor(): void
    {
        $this->reset('editingKey', 'fieldType', 'fieldLabel', 'fieldHelp', 'fieldRequired', 'fieldOptions');
        $this->resetValidation();
    }
}
