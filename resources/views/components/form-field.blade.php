@props([
    'field',
    'model' => null,
    'signatureUrl' => null,
])

@php
    use App\Enums\FormFieldType;

    $type = $field->type;
    // Without a model the field is shown read-only, for the builder preview.
    $bind = new \Illuminate\View\ComponentAttributeBag($model
        ? ['wire:model' => $model]
        : ['disabled' => true]);
@endphp

@switch($type)
    @case(FormFieldType::Heading)
        <div>
            <flux:heading size="lg">{{ $field->label }}</flux:heading>
            @if ($field->help)
                <flux:text class="mt-1">{{ $field->help }}</flux:text>
            @endif
        </div>
        @break

    @case(FormFieldType::Textarea)
        <flux:textarea :attributes="$bind" :label="$field->label" :description="$field->help" :required="$field->required" rows="4" />
        @break

    @case(FormFieldType::Number)
        <flux:input :attributes="$bind" type="number" step="any" :label="$field->label" :description="$field->help" :required="$field->required" />
        @break

    @case(FormFieldType::Date)
        <flux:input :attributes="$bind" type="date" :label="$field->label" :description="$field->help" :required="$field->required" />
        @break

    @case(FormFieldType::Email)
        <flux:input :attributes="$bind" type="email" :label="$field->label" :description="$field->help" :required="$field->required" />
        @break

    @case(FormFieldType::Phone)
        <flux:input :attributes="$bind" type="tel" :label="$field->label" :description="$field->help" :required="$field->required" />
        @break

    @case(FormFieldType::Select)
        <flux:select :attributes="$bind" :label="$field->label" :description="$field->help" :required="$field->required" :placeholder="__('Choose an option')">
            @foreach ($field->options as $option)
                <flux:select.option value="{{ $option['value'] }}">{{ $option['label'] }}</flux:select.option>
            @endforeach
        </flux:select>
        @break

    @case(FormFieldType::Radio)
        <flux:radio.group :attributes="$bind" :label="$field->label" :description="$field->help">
            @foreach ($field->options as $option)
                <flux:radio value="{{ $option['value'] }}" :label="$option['label']" />
            @endforeach
        </flux:radio.group>
        @break

    @case(FormFieldType::Checkboxes)
        <flux:checkbox.group :attributes="$bind" :label="$field->label" :description="$field->help">
            @foreach ($field->options as $option)
                <flux:checkbox value="{{ $option['value'] }}" :label="$option['label']" />
            @endforeach
        </flux:checkbox.group>
        @break

    @case(FormFieldType::Checkbox)
        <flux:checkbox :attributes="$bind" :label="$field->label" :description="$field->help" />
        @break

    @case(FormFieldType::Consent)
        <div class="flex flex-col gap-2">
            @if ($field->help)
                <flux:text class="rounded-lg bg-zinc-50 p-3 whitespace-pre-line dark:bg-zinc-800">{{ $field->help }}</flux:text>
            @endif
            <flux:checkbox :attributes="$bind" :label="$field->label" />
        </div>
        @break

    @case(FormFieldType::Signature)
        <div class="flex flex-col gap-2">
            <flux:label>{{ $field->label }}</flux:label>
            @if ($field->help)
                <flux:text size="sm">{{ $field->help }}</flux:text>
            @endif

            @if ($signatureUrl)
                <img src="{{ $signatureUrl }}" alt="{{ $field->label }}" class="h-32 rounded-lg border border-zinc-200 bg-white dark:border-zinc-700" />
            @endif

            @if ($model)
                <x-signature-pad :model="$model" />
            @else
                <div class="h-32 rounded-lg border border-dashed border-zinc-300 dark:border-zinc-700"></div>
            @endif
        </div>
        @break

    @default
        <flux:input :attributes="$bind" :label="$field->label" :description="$field->help" :required="$field->required" />
@endswitch

@if ($model)
    <flux:error name="{{ $model }}" />
@endif
