<div class="flex w-full flex-col gap-6">
    <x-portal.hero
        :eyebrow="$form->club->name"
        :title="$form->name"
        :subtitle="$form->description"
    >
        <x-slot:actions>
            <flux:button variant="ghost" icon="arrow-left" :href="route('portal.forms')" wire:navigate>{{ __('Back') }}</flux:button>
        </x-slot:actions>
    </x-portal.hero>

    @if ($locked)
        <div class="stagger animate-rise flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/10" style="--d: 80ms">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-600 text-white">
                <flux:icon.check-circle class="size-5" />
            </span>
            <p class="text-sm leading-relaxed text-emerald-900 dark:text-emerald-200">
                {{ __('You sent this form in on :date. Your answers can no longer be changed. Ask your club if something needs correcting.', [
                    'date' => $submission->submitted_at->timezone($form->club->timezone)->isoFormat('D MMMM YYYY HH:mm'),
                ]) }}
            </p>
        </div>

        @php($schema = $this->submittedSchema)

        <div class="stagger animate-rise sp-panel px-5 py-5" style="--d: 140ms">
            <div class="flex flex-col gap-3">
                @foreach ($schema->fields as $fieldIndex => $field)
                    <div class="stagger animate-rise" style="--d: {{ min(600, 180 + $fieldIndex * 50) }}ms">
                        <x-form-answer
                            :key="$field->key"
                            :schema="$schema"
                            :field="$field"
                            :answer="$submission->answer($field->key)"
                            :signature-url="route('portal.forms.signature', [$submission, $field->key])"
                        />
                    </div>
                @endforeach
            </div>
        </div>
    @else
        @if ($submission)
            <div class="stagger animate-rise flex items-start gap-3 rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-500/20 dark:bg-blue-500/10" style="--d: 80ms">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white">
                    <flux:icon.information-circle class="size-5" />
                </span>
                <p class="text-sm leading-relaxed text-blue-900 dark:text-blue-200">
                    {{ __('You filled this form in on :date. You can update your answers below.', [
                        'date' => $submission->submitted_at->timezone($form->club->timezone)->isoFormat('D MMMM YYYY HH:mm'),
                    ]) }}
                </p>
            </div>
        @endif

        <form wire:submit="submit"
              x-data="spFormProgress({{ count($this->fields) }})"
              @input="recount()"
              @change="recount()"
              class="stagger animate-rise sp-panel relative overflow-hidden"
              style="--d: 140ms">

            {{-- Live progress --}}
            <div class="relative flex items-center gap-3 border-b border-zinc-200/70 px-6 py-4 dark:border-zinc-800/70">
                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                    <div class="h-full rounded-full bg-blue-600 transition-[width] duration-500 ease-out"
                         :style="`width: ${percentage}%`"></div>
                </div>
                <span class="text-xs font-semibold text-zinc-500 tabular-nums dark:text-zinc-400">
                    <span x-text="filled">0</span>/<span x-text="total">{{ count($this->fields) }}</span>
                    <span class="ml-1">{{ __('answered') }}</span>
                </span>
            </div>

            <div class="relative flex flex-col gap-6 px-6 py-6">
                @foreach ($this->fields as $fieldIndex => $field)
                    <div data-sp-field class="stagger animate-rise" style="--d: {{ min(600, 180 + $fieldIndex * 50) }}ms">
                        <x-form-field
                            :key="$field->key"
                            :field="$field"
                            :model="'answers.'.$field->key"
                            :signature-url="$submission && $submission->answer($field->key) ? route('portal.forms.signature', [$submission, $field->key]) : null"
                        />
                    </div>
                @endforeach

                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" icon-trailing="arrow-right"
                                >
                        {{ $submission ? __('Save answers') : __('Submit form') }}
                    </flux:button>
                </div>
            </div>
        </form>
    @endif
</div>
