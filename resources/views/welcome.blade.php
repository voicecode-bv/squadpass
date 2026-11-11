@php
    $addonPrices = \App\Models\Addon::landingPrices();
    $faqs = [
        ['question' => __('How do I get started?'), 'answer' => __('Create your club with the form below. A setup wizard guides you through your schedule, strip cards and subscriptions, and then you invite your members. You can start right away, no demo or quote needed.')],
        ['question' => __('How long before I can start?'), 'answer' => __('You can start immediately: your club is ready the moment you create it. Want your own branded app in the App Store and Google Play? It takes about 7 days from your request until your app is live.')],
        ['question' => __('Can I try SquadPass first?'), 'answer' => __('Every new club gets a free trial of :days days with all features. You only choose a plan once you want to keep going.', ['days' => \App\Enums\ClubPlan::TRIAL_DAYS])],
        ['question' => __('How long am I tied to SquadPass?'), 'answer' => __('No long contracts or small print. Your plan and add-ons run per month and can be cancelled monthly. After cancelling you keep using everything until the end of the paid month.')],
        ['question' => __('Can I offer SquadPass under my own brand?'), 'answer' => __('Yes, SquadPass is white label. Upload your own logo (also for dark mode) and choose your brand colour: the app, the member portal, the sign-up and trial lesson pages and every email to your members then use your style. Only a small "Powered by SquadPass" remains.')],
        ['question' => __('Can I import my current members?'), 'answer' => __('Yes. Upload your existing member list as a CSV file, for example an export from Excel or your current system. Names, email addresses, membership numbers, member since, phone numbers and dates of birth come along, and you choose whether members get an invitation right away.')],
        ['question' => __('How do you offer support?'), 'answer' => __('The setup wizard and product tour help you on your way. Still have a question? Create a support ticket from your dashboard or send us an email, and we will get back to you as soon as possible.')],
    ];
@endphp

<x-marketing.layout :title="config('app.name').' | '.__('Software for members, lessons and payments')" :description="__('SquadPass is the all-in-one platform for sports clubs, studios and gyms: members, lesson schedules, strip cards, subscriptions, check-ins and direct debit. Try it free for :days days.', ['days' => \App\Enums\ClubPlan::TRIAL_DAYS])" :canonical="route('home')">
    <x-slot:head>
        @vite('resources/js/hero-club.js')
    </x-slot:head>

    <x-slot:backdrop>
        <x-marketing.hero-club />
    </x-slot:backdrop>

    {{-- Hero: text over the full-width club scene in the backdrop. --}}
    <section class="flex min-h-[max(34rem,calc(100svh-18rem))] items-start pt-6 pb-[14rem] sm:pt-12 lg:min-h-[max(32rem,calc(100svh-19rem))] lg:pb-[15rem]">
        <div class="flex max-w-4xl flex-col items-start gap-6" data-hero-text>
            <h1 class="text-4xl font-bold tracking-tight text-balance text-zinc-900 dark:text-white sm:text-6xl">
                {{ __('Everything your club needs,') }}
                <span class="bg-linear-to-r from-blue-600 dark:from-blue-400 via-indigo-600 dark:via-indigo-400 to-sky-500 dark:to-sky-400 bg-clip-text text-transparent">{{ __('in one pass.') }}</span>
            </h1>

            <p class="max-w-2xl text-lg leading-relaxed text-zinc-600 dark:text-zinc-400">
                {{ __('Members, lessons, strip cards, subscriptions and check-ins. SquadPass handles the admin so you can focus on your club.') }}
            </p>

            <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                @guest
                    <a href="#signup" class="shrink-0 rounded-xl bg-linear-to-r from-blue-500 to-indigo-600 px-4 py-3 text-sm font-semibold sm:px-6 sm:text-base text-white shadow-lg shadow-blue-500/40 transition hover:from-blue-400 hover:to-indigo-500">
                        {{ __('Get started') }}
                    </a>
                    <livewire:clubs.start-demo />
                @else
                    <a href="{{ route('dashboard') }}" class="rounded-xl px-6 py-3 text-base font-semibold text-zinc-700 dark:text-zinc-300 ring-1 ring-zinc-300 dark:ring-white/15 transition hover:bg-zinc-100 dark:hover:bg-white/5 hover:text-zinc-900 dark:hover:text-white">
                        {{ __('Dashboard') }}
                    </a>
                @endguest
            </div>
        </div>
    </section>

    {{-- Features: starts right below the hero, so it shows above the fold. --}}
    <section class="pt-10 pb-16 lg:pb-24">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-zinc-900 dark:text-white sm:text-4xl">{{ __('One platform, your whole club') }}</h2>
            <p class="mt-4 text-lg text-zinc-600 dark:text-zinc-400">{{ __('From the front desk to the locker room, every part of your club runs through SquadPass.') }}</p>
        </div>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['feature' => \App\Enums\MarketingFeature::MemberManagement, 'icon' => 'users', 'title' => __('Member management'), 'text' => __('Invite members, track memberships and keep every detail in one place.')],
                ['feature' => \App\Enums\MarketingFeature::LessonSchedule, 'icon' => 'calendar-days', 'title' => __('Lessons & schedules'), 'text' => __('Plan recurring lessons, manage capacity and let members enroll themselves.')],
                ['feature' => \App\Enums\MarketingFeature::StripCards, 'icon' => 'ticket', 'title' => __('Strip cards'), 'text' => __('Sell digital strip cards that members redeem with a single check-in.')],
                ['feature' => \App\Enums\MarketingFeature::DirectDebit, 'icon' => 'credit-card', 'title' => __('Subscriptions & payments'), 'text' => __('Monthly and yearly plans with automatic credit tracking. Add online payments when you are ready.')],
                ['feature' => \App\Enums\MarketingFeature::CheckIn, 'icon' => 'finger-print', 'title' => __('Check-in desk'), 'text' => __('Check members in at the door and instantly see their coverage.')],
                ['feature' => \App\Enums\MarketingFeature::LessonSchedule, 'icon' => 'device-phone-mobile', 'title' => __('Member portal'), 'text' => __('Members book lessons, buy cards and follow their visits from any device.')],
            ] as $feature)
                <a href="{{ route('features.show', $feature['feature']) }}" class="group rounded-2xl bg-white dark:bg-white/5 p-6 ring-1 ring-zinc-200 dark:ring-white/10 transition hover:bg-zinc-50 dark:hover:bg-white/10 hover:ring-blue-400/40">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-blue-500/15 text-blue-600 dark:text-blue-300 ring-1 ring-blue-400/30 transition group-hover:bg-blue-500 group-hover:text-white">
                        <flux:icon :icon="$feature['icon']" class="size-5" />
                    </span>
                    <h3 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">{{ $feature['title'] }}</h3>
                    <p class="mt-2 leading-relaxed text-zinc-600 dark:text-zinc-400">{{ $feature['text'] }}</p>
                </a>
            @endforeach
        </div>

        <div class="mt-10 text-center">
            <a href="{{ route('features.index') }}" class="inline-flex items-center gap-1.5 font-semibold text-blue-600 dark:text-blue-300 transition hover:text-zinc-900 dark:hover:text-white">
                {{ __('View all features') }}
                <flux:icon.arrow-right variant="micro" class="size-4" />
            </a>
        </div>
    </section>

    <x-marketing.white-label />

    <x-marketing.segments />

    <x-marketing.pricing :addon-prices="$addonPrices" />

    <x-marketing.add-ons :addon-prices="$addonPrices" />

    <x-marketing.faq :items="$faqs" />

    <x-marketing.signup />
</x-marketing.layout>
