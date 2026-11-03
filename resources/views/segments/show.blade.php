@php
    /** @var \App\Enums\ClubSegment $segment */
    [$headline, $highlight] = $segment->headline();
    $schedule = $segment->exampleSchedule();
    $addonPrices = \App\Models\Addon::landingPrices();
    $days = \App\Enums\ClubPlan::TRIAL_DAYS;
    $faqs = [
        ...$segment->faqs(),
        ['question' => __('Can I try SquadPass first?'), 'answer' => __('Every new club gets a free trial of :days days with all features. You only choose a plan once you want to keep going.', ['days' => $days])],
        ['question' => __('How long am I tied to SquadPass?'), 'answer' => __('No long contracts or small print. Your plan and add-ons run per month and can be cancelled monthly. After cancelling you keep using everything until the end of the paid month.')],
        ['question' => __('Can I import my current members?'), 'answer' => __('Yes. Upload your existing member list as a CSV file, for example an export from Excel or your current system. Names, email addresses, membership numbers, member since, phone numbers and dates of birth come along, and you choose whether members get an invitation right away.')],
    ];
@endphp

<x-marketing.layout :title="$segment->metaTitle().' | '.config('app.name')" :description="$segment->metaDescription()" :canonical="route('segments.show', $segment)">
    {{-- Hero --}}
    <section class="grid items-center gap-16 py-16 lg:grid-cols-2 lg:py-24">
        <div class="flex flex-col items-start gap-6">
            <span class="inline-flex items-center gap-2 rounded-full bg-blue-500/10 px-4 py-1.5 text-sm font-medium text-blue-300 ring-1 ring-blue-400/30">
                <flux:icon :icon="$segment->icon()" variant="micro" class="size-4" />
                {{ $segment->eyebrow() }}
            </span>

            <h1 class="text-4xl font-bold tracking-tight text-white sm:text-6xl">
                {{ $headline }}
                <span class="bg-linear-to-r from-blue-400 via-indigo-400 to-sky-400 bg-clip-text text-transparent">{{ $highlight }}</span>
            </h1>

            <p class="max-w-xl text-lg leading-relaxed text-zinc-400">{{ $segment->intro() }}</p>

            <x-marketing.hero-actions />
        </div>

        {{-- Schedule mockup --}}
        <div class="relative mx-auto w-full max-w-md" aria-hidden="true">
            <div class="absolute inset-0 rotate-3 rounded-3xl bg-linear-to-br from-sky-500/30 to-indigo-600/30 blur-sm"></div>
            <div class="relative -rotate-1 rounded-3xl bg-zinc-900/90 p-6 shadow-2xl shadow-blue-950/60 ring-1 ring-white/15 transition duration-500 hover:rotate-0">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs tracking-widest text-zinc-500 uppercase">{{ __('Today') }}</p>
                        <p class="mt-1 font-semibold text-white">{{ $schedule['club'] }}</p>
                    </div>
                    <span class="flex size-10 items-center justify-center rounded-xl bg-blue-600">
                        <x-app-logo-icon class="size-6 fill-current text-white" />
                    </span>
                </div>

                <ul class="mt-6 space-y-3">
                    @foreach ($schedule['lessons'] as $lesson)
                        <li class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm text-zinc-400">{{ $lesson['time'] }}</p>
                                    <p class="font-semibold text-white">{{ $lesson['name'] }}</p>
                                </div>
                                @if ($lesson['booked'] >= $lesson['capacity'])
                                    <span class="rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-semibold text-emerald-300 ring-1 ring-emerald-400/30">{{ __('Full') }}</span>
                                @else
                                    <span class="text-sm font-medium text-zinc-300">{{ $lesson['booked'] }}/{{ $lesson['capacity'] }}</span>
                                @endif
                            </div>
                            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-white/10">
                                <div class="h-full rounded-full bg-linear-to-r from-blue-500 to-indigo-500" style="width: {{ (int) round($lesson['booked'] / $lesson['capacity'] * 100) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- Pain points --}}
    <section class="py-16 lg:py-24">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ __('Sounds familiar?') }}</h2>
            <p class="mt-4 text-lg text-zinc-400">{{ __('The admin that keeps you from your real work, solved in one platform.') }}</p>
        </div>

        <div class="mt-14 grid gap-6 lg:grid-cols-3">
            @foreach ($segment->painPoints() as $pain)
                <div class="rounded-2xl bg-white/5 p-6 ring-1 ring-white/10">
                    <p class="flex items-center gap-2 text-sm font-medium text-zinc-500 line-through decoration-zinc-600">
                        <flux:icon.x-circle variant="micro" class="size-4 shrink-0 text-zinc-500" />
                        {{ $pain['title'] }}
                    </p>
                    <p class="mt-4 flex gap-3 leading-relaxed text-zinc-300">
                        <flux:icon.check-circle variant="micro" class="mt-1 size-5 shrink-0 text-blue-300" />
                        <span>{{ $pain['text'] }}</span>
                    </p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Features --}}
    <section class="py-16 lg:py-24">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ __('Everything you need, nothing you don\'t') }}</h2>
            <p class="mt-4 text-lg text-zinc-400">{{ __('Every plan includes all core features. Extras such as online payments you switch on whenever you like.') }}</p>
        </div>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($segment->features() as $feature)
                <div class="group rounded-2xl bg-white/5 p-6 ring-1 ring-white/10 transition hover:bg-white/10 hover:ring-blue-400/40">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-blue-500/15 text-blue-300 ring-1 ring-blue-400/30 transition group-hover:bg-blue-500 group-hover:text-white">
                        <flux:icon :icon="$feature['icon']" class="size-5" />
                    </span>
                    <h3 class="mt-4 text-lg font-semibold text-white">{{ $feature['title'] }}</h3>
                    <p class="mt-2 leading-relaxed text-zinc-400">{{ $feature['text'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <x-marketing.how-it-works />

    <x-marketing.pricing :addon-prices="$addonPrices" />

    <x-marketing.add-ons :addon-prices="$addonPrices" />

    <x-marketing.faq :items="$faqs" />

    <x-marketing.signup :title="$segment->signupTitle()" :text="__('Create your club in minutes and try every feature free for :days days.', ['days' => $days])" />

    <x-marketing.features />

    <x-marketing.segments :except="$segment" :title="__('SquadPass for every club')" />
</x-marketing.layout>
