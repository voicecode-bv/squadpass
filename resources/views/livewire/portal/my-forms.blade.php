<div class="flex w-full flex-col gap-6">
    @php($openForms = $this->forms->reject(fn ($form) => (bool) $this->submissions->get($form->id)?->locks($form)))

    <x-portal.hero
        :eyebrow="__('Paperwork')"
        :title="__('My forms')"
        :subtitle="__('Forms your clubs ask you to fill in.')"
    >
        @if ($this->forms->isNotEmpty())
            <div class="grid gap-3 sm:grid-cols-2">
                <x-portal.stat :count-up="$openForms->count()" :value="$openForms->count()" :label="__('Still to fill in')" :delay="260" />
                <x-portal.stat :count-up="$this->forms->count() - $openForms->count()" :value="$this->forms->count() - $openForms->count()" :label="__('Sent in')" :delay="320" />
            </div>
        @endif
    </x-portal.hero>

    @if ($this->forms->isEmpty())
        <x-portal.empty icon="clipboard-document-list" :heading="__('No forms')" :text="__('There is nothing for you to fill in right now.')" />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->forms as $formIndex => $form)
                @php($submission = $this->submissions->get($form->id))
                @php($locked = (bool) $submission?->locks($form))

                <x-portal.card :delay="min(600, $formIndex * 80)" wire:key="form-{{ $form->id }}" class="flex flex-col gap-4 p-6">
                    <span class="absolute inset-x-0 top-0 h-1 {{ $locked ? 'bg-emerald-500' : 'bg-amber-500' }}" aria-hidden="true"></span>

                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <flux:text class="text-sm">{{ $form->club->name }}</flux:text>
                            <flux:heading class="sp-card-title truncate">{{ $form->name }}</flux:heading>
                        </div>

                        @if ($locked)
                            <flux:badge size="sm" color="green">{{ __('Sent in') }}</flux:badge>
                        @elseif ($submission)
                            <flux:badge size="sm" color="amber">{{ __('Fill in again') }}</flux:badge>
                        @else
                            <span class="relative shrink-0">
                                <span class="sp-ping absolute inset-0 rounded-full text-amber-500/70" aria-hidden="true"></span>
                                <flux:badge size="sm" color="amber">{{ __('Not filled in yet') }}</flux:badge>
                            </span>
                        @endif
                    </div>

                    @if ($locked)
                        <flux:text class="text-xs">
                            {{ __('Sent in on :date', ['date' => $submission->submitted_at->timezone($form->club->timezone)->isoFormat('D MMMM YYYY')]) }}
                        </flux:text>
                    @endif

                    <div class="mt-auto pt-2">
                        <flux:button size="sm" :variant="$locked ? 'ghost' : 'primary'" :href="route('portal.forms.fill', $form)" wire:navigate
                                     icon-trailing="arrow-right">
                            {{ $locked ? __('View') : __('Fill in') }}
                        </flux:button>
                    </div>
                </x-portal.card>
            @endforeach
        </div>
    @endif
</div>
