@php
    $schema = $submission->version->schemaObject();
@endphp

<div class="flex w-full flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ $submission->user->name }}</flux:heading>
            <flux:text class="mt-1">
                {{ __(':form, filled in on :date', [
                    'form' => $form->name,
                    'date' => $submission->submitted_at->timezone($club->timezone)->isoFormat('D MMMM YYYY HH:mm'),
                ]) }}
            </flux:text>
        </div>

        <div class="flex items-center gap-2">
            @if ($this->locksForm)
                <flux:button size="sm" icon="arrow-path" wire:click="reopen" wire:confirm="{{ __('Let :name fill this form in again? Their current response is kept as history.', ['name' => $submission->user->name]) }}">
                    {{ __('Reopen') }}
                </flux:button>
            @endif

            <flux:button variant="ghost" :href="route('clubs.forms.submissions', [$club, $form])" wire:navigate>{{ __('Back') }}</flux:button>
        </div>
    </div>

    @if ($submission->reopened_at)
        <flux:callout icon="arrow-path" color="zinc">
            <flux:callout.text>
                {{ __('Reopened on :date by :name. The member can fill this form in again.', [
                    'date' => $submission->reopened_at->timezone($club->timezone)->isoFormat('D MMMM YYYY HH:mm'),
                    'name' => $submission->reopenedBy?->name ?? __('a removed account'),
                ]) }}
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->isOutdated)
        <flux:callout icon="information-circle" color="zinc">
            <flux:callout.text>
                {{ __('This response was filled in with version :number and is shown exactly as it was then. The form has changed since.', ['number' => $submission->version->version]) }}
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="sp-panel p-5">
        <div class="flex flex-col gap-3">
            @foreach ($schema->fields as $field)
                <x-form-answer
                    :key="$field->key"
                    :schema="$schema"
                    :field="$field"
                    :answer="$submission->answer($field->key)"
                    :signature-url="route('portal.forms.signature', [$submission, $field->key])"
                />
            @endforeach
        </div>
    </div>

    @if ($this->history->isNotEmpty())
        <div class="sp-panel p-5">
            <flux:heading size="lg">{{ __('Earlier responses') }}</flux:heading>

            <div class="mt-3 flex flex-col gap-2">
                @foreach ($this->history as $earlier)
                    <div wire:key="history-{{ $earlier->id }}" class="flex items-center justify-between gap-4 rounded-lg border border-zinc-200 px-4 py-3 dark:border-zinc-700">
                        <div>
                            <flux:text class="font-normal">{{ $earlier->submitted_at->timezone($club->timezone)->isoFormat('D MMMM YYYY HH:mm') }}</flux:text>
                            <flux:text size="sm">{{ __('Version :number', ['number' => $earlier->version->version]) }}</flux:text>
                        </div>

                        <flux:button variant="ghost" size="sm" :href="route('clubs.forms.submissions.show', [$club, $form, $earlier])" wire:navigate>
                            {{ __('View') }}
                        </flux:button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
