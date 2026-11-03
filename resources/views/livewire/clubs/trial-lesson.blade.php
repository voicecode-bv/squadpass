<div class="mx-auto mt-16 flex w-full max-w-lg flex-col gap-6 px-4">
    <div class="flex flex-col gap-2 text-center">
        <flux:heading size="xl">{{ $club->name }}</flux:heading>
        <flux:text>{{ __('Try a lesson at :club. Pick a moment and leave your details; we will email you a confirmation.', ['club' => $club->name]) }}</flux:text>
    </div>

    @if ($booked || $paid)
        <section class="sp-panel flex flex-col gap-3 p-5 text-center">
            <h2 class="sp-card-title">{{ __('See you soon!') }}</h2>
            <flux:text>
                {{ $booked
                    ? __('Your trial lesson is booked. Check your inbox for the confirmation.')
                    : __('Thank you for your payment. You will receive a confirmation by email as soon as it has been processed.') }}
            </flux:text>
        </section>
    @elseif ($this->occurrences->isEmpty())
        <x-portal.empty :heading="__('No trial lessons available')" :text="__('There are no trial lessons with room left in the next two weeks. Contact the club or check back later.')" />
    @else
        <form wire:submit="book($event.target.querySelector('[name=cf-turnstile-response]')?.value ?? null)" class="relative flex flex-col gap-6">
            <section class="sp-panel flex flex-col gap-4 p-5">
                <h2 class="sp-card-title">{{ __('Choose a moment') }}</h2>

                <flux:radio.group wire:model.live="occurrenceId" :label="__('Lesson')" label:sr-only>
                    @foreach ($this->occurrences as $occurrence)
                        @php
                            $startsAt = $occurrence->starts_at->timezone($club->timezone);
                        @endphp
                        <flux:radio
                            wire:key="occurrence-{{ $occurrence->id }}"
                            :value="$occurrence->id"
                            :label="ucfirst($startsAt->translatedFormat('l j F · H:i')).' — '.$occurrence->lesson->name"
                            :description="$occurrence->lesson->hasPaidTrial() ? $occurrence->lesson->formattedTrialPrice() : __('Free')"
                        />
                    @endforeach
                </flux:radio.group>
            </section>

            <section class="sp-panel flex flex-col gap-4 p-5">
                <h2 class="sp-card-title">{{ __('Your details') }}</h2>

                <flux:input wire:model="first_name" :label="__('First name')" type="text" required autocomplete="given-name" />

                <div class="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-4">
                    <flux:input wire:model="name_prefix" :label="__('Name prefix')" type="text" autocomplete="additional-name" :placeholder="__('e.g. van der')" />
                    <flux:input wire:model="last_name" :label="__('Last name')" type="text" required autocomplete="family-name" />
                </div>

                <flux:input wire:model="email" :label="__('Email address')" type="email" required autocomplete="email" placeholder="email@example.com" />
                <flux:input wire:model="phone" :label="__('Phone (optional)')" type="tel" autocomplete="tel" />

                <x-spam-protection livewire />
            </section>

            <flux:button type="submit" variant="primary" class="w-full">
                @if ($this->selectedOccurrence()?->lesson->hasPaidTrial())
                    {{ __('Continue to payment') }}
                @else
                    {{ __('Book trial lesson') }}
                @endif
            </flux:button>
        </form>
    @endif
</div>
