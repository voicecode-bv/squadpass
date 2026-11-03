@php
    $addonPrices = \App\Models\Addon::landingPrices();
    $days = \App\Enums\ClubPlan::TRIAL_DAYS;
@endphp

<x-marketing.layout :title="__('All features for your club').' | '.config('app.name')" :description="__('Member management, lesson schedule, strip cards, direct debit, QR check-in, trial lessons, digital forms and white label: every SquadPass feature for sports clubs, studios and gyms.')" :canonical="route('features.index')">
    <section class="py-16 lg:py-24">
        <div class="mx-auto flex max-w-3xl flex-col items-center gap-6 text-center">
            <h1 class="text-4xl font-bold tracking-tight text-white sm:text-6xl">
                {{ __('Everything your club needs,') }}
                <span class="bg-linear-to-r from-blue-400 via-indigo-400 to-sky-400 bg-clip-text text-transparent">{{ __('in one pass.') }}</span>
            </h1>
            <p class="text-lg leading-relaxed text-zinc-400">{{ __('From the first trial lesson to the monthly direct debit: discover what SquadPass does for your club, studio or gym.') }}</p>
        </div>
    </section>

    <x-marketing.features :title="__('All features')" />

    <x-marketing.segments />

    <x-marketing.pricing :addon-prices="$addonPrices" />

    <x-marketing.signup :text="__('Create your club in minutes and try every feature free for :days days.', ['days' => $days])" />
</x-marketing.layout>
