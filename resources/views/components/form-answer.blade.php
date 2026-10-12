@props([
    'schema',
    'field',
    'answer' => null,
    'signatureUrl' => null,
])

@php
    use App\Enums\FormFieldType;
@endphp

@if ($field->type === FormFieldType::Heading)
    <flux:heading size="lg" class="mt-2">{{ $field->label }}</flux:heading>
@else
    <div class="flex flex-col gap-1 border-b border-zinc-100 pb-3 last:border-0 dark:border-zinc-800">
        <flux:text size="sm">{{ $field->label }}</flux:text>

        @if ($field->type === FormFieldType::Signature && $signatureUrl && $answer)
            <img src="{{ $signatureUrl }}" alt="{{ $field->label }}" class="h-24 w-fit rounded-lg border border-zinc-200 bg-white dark:border-zinc-700" />
        @elseif ($field->type === FormFieldType::Signature)
            <flux:text class="font-normal">{{ $answer ? __('Signed') : '—' }}</flux:text>
        @else
            <flux:text class="font-normal whitespace-pre-line">{{ $schema->formatAnswer($field, $answer) }}</flux:text>
        @endif
    </div>
@endif
