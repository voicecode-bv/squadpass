{{-- display: contents makes the button a sibling of the other hero buttons, so they stay on one row --}}
<div class="contents">
    @php
        $buttonClasses = 'inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-zinc-800 dark:text-zinc-200 ring-1 ring-zinc-300 dark:ring-white/15 transition hover:bg-zinc-100 dark:hover:bg-white/5 hover:text-zinc-900 dark:hover:text-white disabled:cursor-wait disabled:opacity-70 sm:px-6 sm:text-base';
    @endphp

    @if ($canResume)
        {{-- They already left their details, so their demo reopens right away. --}}
        <button type="button" wire:click="start" wire:loading.attr="disabled" class="{{ $buttonClasses }}">
            <span wire:loading.remove wire:target="start" class="inline-flex items-center gap-2">
                <flux:icon.play variant="micro" class="size-4" />
                {{ __('Continue your demo') }}
            </span>
            <span wire:loading.flex wire:target="start" class="items-center gap-2">
                <flux:icon.loading variant="micro" class="size-4" />
                {{ __('Preparing your demo…') }}
            </span>
        </button>
    @else
        <flux:modal.trigger name="start-demo">
            <button type="button" class="{{ $buttonClasses }}" data-test="open-demo-modal">
                <flux:icon.play variant="micro" class="size-4" />
                {{ __('Try the demo') }}
            </button>
        </flux:modal.trigger>

        <flux:modal name="start-demo" class="w-full md:w-[30rem]">
            <form wire:submit="start($event.target.querySelector('[name=cf-turnstile-response]')?.value ?? null)" class="relative space-y-6">
                <div>
                    <flux:heading size="lg">{{ __('Try the demo') }}</flux:heading>
                    <flux:text class="mt-2">{{ __('Leave your email address and tell us what kind of club you have. Then you open a demo club full of example data right away.') }}</flux:text>
                </div>

                <flux:input
                    wire:model="email"
                    :label="__('Email address')"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="email@example.com"
                />

                <flux:select wire:model.live="club_type" :label="__('Type of club')" :placeholder="__('Choose your type of club')" required>
                    @foreach ($this->clubTypeOptions() as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                @if ($club_type === \App\Models\DemoRequest::OTHER_CLUB_TYPE)
                    <flux:input
                        wire:model="other_club_type"
                        :label="__('What kind of club is it?')"
                        type="text"
                        required
                        maxlength="100"
                        :placeholder="__('e.g. swimming school')"
                    />
                @endif

                <x-spam-protection livewire />

                <flux:button type="submit" variant="primary" class="w-full" data-test="start-demo-button">
                    {{ __('Open the demo') }}
                </flux:button>
            </form>
        </flux:modal>
    @endif
</div>
