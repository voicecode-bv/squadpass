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

                <x-portal.card :delay="min(600, $formIndex * 80)" wire:key="form-{{ $form->id }}" data-test="form-card-{{ $form->id }}">
                    <a href="{{ route('portal.forms.fill', $form) }}" wire:navigate class="flex h-full flex-col">
                        <div class="flex flex-1 flex-col gap-1 p-5">
                            <h3 class="text-base leading-tight font-semibold text-zinc-900 transition group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">{{ $form->name }}</h3>
                            <p class="sp-card-subtitle">
                                {{ $form->club->name }} ·
                                @if ($locked)
                                    {{ __('Sent in on :date', ['date' => $submission->submitted_at->timezone($form->club->timezone)->isoFormat('D MMMM YYYY')]) }}
                                @elseif ($submission)
                                    {{ __('Fill in again') }}
                                @else
                                    {{ __('Not filled in yet') }}
                                @endif
                            </p>
                        </div>

                        <div @class([
                            'flex items-center justify-between border-t border-zinc-100 px-5 py-3 text-sm font-medium dark:border-white/5',
                            'text-blue-600 dark:text-blue-400' => ! $locked,
                            'text-zinc-500 dark:text-zinc-400' => $locked,
                        ])>
                            <span>{{ $locked ? __('View') : __('Fill in') }}</span>
                            <span aria-hidden="true" class="transition duration-200 group-hover:translate-x-1">&rarr;</span>
                        </div>
                    </a>
                </x-portal.card>
            @endforeach
        </div>
    @endif
</div>
