@php
    /** @var \App\Enums\MarketingFeature $feature */
    [$headline, $highlight] = $feature->headline();
    $addonPrices = \App\Models\Addon::landingPrices();
    $days = \App\Enums\ClubPlan::TRIAL_DAYS;
    $faqs = [
        ...$feature->faqs(),
        ['question' => __('Can I try SquadPass first?'), 'answer' => __('Every new club gets a free trial of :days days with all features. You only choose a plan once you want to keep going.', ['days' => $days])],
        ['question' => __('How long am I tied to SquadPass?'), 'answer' => __('No long contracts or small print. Your plan and add-ons run per month and can be cancelled monthly. After cancelling you keep using everything until the end of the paid month.')],
    ];
@endphp

<x-marketing.layout :title="$feature->metaTitle().' | '.config('app.name')" :description="$feature->metaDescription()" :canonical="route('features.show', $feature)">
    {{-- Hero --}}
    <section class="grid items-center gap-16 py-16 lg:grid-cols-2 lg:py-24">
        <div class="flex flex-col items-start gap-6">
            <h1 class="text-4xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-6xl">
                {{ $headline }}
                <span class="bg-linear-to-r from-blue-600 dark:from-blue-400 via-indigo-600 dark:via-indigo-400 to-sky-500 dark:to-sky-400 bg-clip-text text-transparent">{{ $highlight }}</span>
            </h1>

            <p class="max-w-xl text-lg leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $feature->intro() }}</p>

            <x-marketing.hero-actions />
        </div>

        {{-- Summary card of what the feature brings; decorative. --}}
        <div class="relative mx-auto w-full max-w-md" aria-hidden="true">
            <div class="absolute inset-0 rotate-3 rounded-3xl bg-linear-to-br from-sky-500/30 to-indigo-600/30 blur-sm"></div>
            <div class="relative -rotate-1 rounded-3xl bg-zinc-900/90 p-6 shadow-2xl shadow-blue-950/60 ring-1 ring-white/15 transition duration-500 hover:rotate-0">
                <div class="flex items-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-blue-600 text-white">
                        <flux:icon :icon="$feature->icon()" class="size-5" />
                    </span>
                    <p class="font-semibold text-white">{{ $feature->label() }}</p>
                </div>

                <ul class="mt-6 space-y-3">
                    @foreach ($feature->benefits() as $benefit)
                        <li class="flex items-center gap-3 rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                            <flux:icon.check-circle variant="mini" class="size-5 shrink-0 text-blue-300" />
                            <span class="font-medium text-zinc-200">{{ $benefit['title'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- Benefits --}}
    <section class="py-16 lg:py-24">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-4xl">{{ __('How it works in SquadPass') }}</h2>
            <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">{{ __('Included in every plan, ready to use the moment you create your club.') }}</p>
        </div>

        <div class="mt-14 grid gap-6 sm:grid-cols-2">
            @foreach ($feature->benefits() as $benefit)
                <div class="group rounded-2xl bg-white dark:bg-white/5 p-6 ring-1 ring-zinc-200 dark:ring-white/10 transition hover:bg-zinc-50 dark:hover:bg-white/10 hover:ring-blue-400/40">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-blue-500/15 text-blue-600 dark:text-blue-300 ring-1 ring-blue-400/30 transition group-hover:bg-blue-500 group-hover:text-white">
                        <flux:icon :icon="$benefit['icon']" class="size-5" />
                    </span>
                    <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">{{ $benefit['title'] }}</h3>
                    <p class="mt-2 leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $benefit['text'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <x-marketing.how-it-works />

    <x-marketing.pricing :addon-prices="$addonPrices" />

    <x-marketing.add-ons :addon-prices="$addonPrices" />

    <x-marketing.faq :items="$faqs" />

    <x-marketing.signup :text="__('Create your club in minutes and try every feature free for :days days.', ['days' => $days])" />

    <x-marketing.features :except="$feature" :title="__('More from SquadPass')" />

    <x-marketing.segments />
</x-marketing.layout>
