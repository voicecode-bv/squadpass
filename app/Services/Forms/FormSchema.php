<?php

namespace App\Services\Forms;

use App\Enums\FormFieldType;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The field layout of a single form version. Stored as JSON on
 * form_versions.schema and never mutated once that version is published.
 */
class FormSchema
{
    /**
     * @param  list<FormField>  $fields
     */
    public function __construct(public array $fields = []) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(array_values(array_map(
            fn (array $field): FormField => FormField::fromArray($field),
            $data['fields'] ?? [],
        )));
    }

    /**
     * @return array{fields: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'fields' => array_map(fn (FormField $field): array => $field->toArray(), $this->fields),
        ];
    }

    public function isEmpty(): bool
    {
        return $this->fields === [];
    }

    public function field(string $key): ?FormField
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Fields that hold an answer, so headings are skipped.
     *
     * @return list<FormField>
     */
    public function answerableFields(): array
    {
        return array_values(array_filter($this->fields, fn (FormField $field): bool => $field->type->storesAnswer()));
    }

    /**
     * Start from previously given answers where this version still has the
     * same field; fields added since stay empty. Field keys are never reused,
     * so an answer can only ever land back on the field it was given for.
     *
     * @param  array<string, mixed>  $previous
     * @return array<string, mixed>
     */
    public function prefill(array $previous = []): array
    {
        $answers = [];

        foreach ($this->answerableFields() as $field) {
            $answers[$field->key] = match ($field->type) {
                FormFieldType::Checkboxes => array_values((array) ($previous[$field->key] ?? [])),
                FormFieldType::Checkbox, FormFieldType::Consent => (bool) ($previous[$field->key] ?? false),
                default => $previous[$field->key] ?? '',
            };
        }

        return $answers;
    }

    /**
     * Validation rules for an answers array, keyed by "{$prefix}.{$fieldKey}".
     *
     * @return array<string, mixed>
     */
    public function validationRules(string $prefix = 'answers'): array
    {
        $rules = [];

        foreach ($this->answerableFields() as $field) {
            $attribute = $prefix.'.'.$field->key;
            $presence = $this->presenceRule($field);

            $rules[$attribute] = match ($field->type) {
                FormFieldType::Text => [$presence, 'string', 'max:'.(int) $field->setting('max', 255)],
                FormFieldType::Textarea => [$presence, 'string', 'max:'.(int) $field->setting('max', 5000)],
                FormFieldType::Number => array_values(array_filter([
                    $presence,
                    'numeric',
                    $field->setting('min') !== null ? 'min:'.$field->setting('min') : null,
                    $field->setting('max') !== null ? 'max:'.$field->setting('max') : null,
                ])),
                FormFieldType::Date => [$presence, 'date'],
                FormFieldType::Email => [$presence, 'email', 'max:255'],
                FormFieldType::Phone => [$presence, 'string', 'max:30'],
                FormFieldType::Select, FormFieldType::Radio => [$presence, 'string', Rule::in($field->optionValues())],
                FormFieldType::Checkboxes => [$presence, 'array'],
                FormFieldType::Checkbox => [$field->required ? 'accepted' : 'nullable', 'boolean'],
                FormFieldType::Consent => [$field->required ? 'accepted' : 'nullable', 'boolean'],
                FormFieldType::Signature => [$presence, 'string'],
                FormFieldType::Heading => ['nullable'],
            };

            if ($field->type === FormFieldType::Checkboxes) {
                $rules[$attribute.'.*'] = ['string', Rule::in($field->optionValues())];
            }
        }

        return $rules;
    }

    /**
     * Human readable attribute names, so validation messages show the label
     * instead of "answers.01JB...".
     *
     * @return array<string, string>
     */
    public function validationAttributes(string $prefix = 'answers'): array
    {
        $attributes = [];

        foreach ($this->answerableFields() as $field) {
            $attributes[$prefix.'.'.$field->key] = $field->label;
        }

        return $attributes;
    }

    /**
     * Render an answer for display, using this version's labels.
     */
    public function formatAnswer(FormField $field, mixed $answer): string
    {
        if ($answer === null || $answer === '' || $answer === []) {
            return '—';
        }

        return match ($field->type) {
            FormFieldType::Select, FormFieldType::Radio => $field->optionLabel((string) $answer),
            FormFieldType::Checkboxes => implode(', ', array_map(
                fn (string $value): string => $field->optionLabel($value),
                array_values((array) $answer),
            )),
            FormFieldType::Checkbox, FormFieldType::Consent => $answer ? __('Yes') : __('No'),
            FormFieldType::Date => Carbon::parse((string) $answer)->format('d-m-Y'),
            default => (string) $answer,
        };
    }

    private function presenceRule(FormField $field): string
    {
        return $field->required ? 'required' : 'nullable';
    }
}
