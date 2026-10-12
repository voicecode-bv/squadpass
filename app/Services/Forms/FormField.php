<?php

namespace App\Services\Forms;

use App\Enums\FormFieldType;
use Illuminate\Support\Str;

/**
 * One field of a form version. Immutable: a published version is never
 * changed, editing a form always produces a new version.
 *
 * @phpstan-type FieldOption array{value: string, label: string}
 * @phpstan-type FieldArray array{key: string, type: string, label: string, help: string|null, required: bool, options: list<FieldOption>, settings: array<string, mixed>}
 */
class FormField
{
    /**
     * @param  list<FieldOption>  $options
     * @param  array<string, mixed>  $settings
     */
    public function __construct(
        public string $key,
        public FormFieldType $type,
        public string $label,
        public ?string $help = null,
        public bool $required = false,
        public array $options = [],
        public array $settings = [],
    ) {}

    /**
     * Field keys are never reused, so answers stay tied to the field they
     * were given for, even when the label changes in a later version.
     */
    public static function newKey(): string
    {
        return (string) Str::ulid();
    }

    /**
     * @param  FieldArray|array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            key: (string) $data['key'],
            type: $data['type'] instanceof FormFieldType ? $data['type'] : FormFieldType::from((string) $data['type']),
            label: (string) $data['label'],
            help: isset($data['help']) && $data['help'] !== '' ? (string) $data['help'] : null,
            required: (bool) ($data['required'] ?? false),
            options: array_values(array_map(
                fn (array $option): array => ['value' => (string) $option['value'], 'label' => (string) $option['label']],
                $data['options'] ?? [],
            )),
            settings: $data['settings'] ?? [],
        );
    }

    /**
     * @return FieldArray
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type->value,
            'label' => $this->label,
            'help' => $this->help,
            'required' => $this->required,
            'options' => $this->options,
            'settings' => $this->settings,
        ];
    }

    /**
     * @return list<string>
     */
    public function optionValues(): array
    {
        return array_map(fn (array $option): string => $option['value'], $this->options);
    }

    /**
     * The label as it was configured when this version was published, or the
     * raw value when the option no longer exists.
     */
    public function optionLabel(string $value): string
    {
        foreach ($this->options as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return $value;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }
}
