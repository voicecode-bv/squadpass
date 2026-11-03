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
    {{-- Hero --}}
    <section class="grid items-center gap-16 py-16 lg:grid-cols-2 lg:py-24">
        <div class="flex flex-col items-start gap-6">
            <span class="inline-flex items-center gap-2 rounded-full bg-blue-500/10 px-4 py-1.5 text-sm font-medium text-blue-300 ring-1 ring-blue-400/30">
                <flux:icon.bolt variant="micro" class="size-4" />
                {{ __('Club management, lessons & payments') }}
            </span>

            <h1 class="text-4xl font-bold tracking-tight text-white sm:text-6xl">
                {{ __('Everything your club needs,') }}
                <span class="bg-linear-to-r from-blue-400 via-indigo-400 to-sky-400 bg-clip-text text-transparent">{{ __('in one pass.') }}</span>
            </h1>

            <p class="max-w-xl text-lg leading-relaxed text-zinc-400">
                {{ __('Members, lessons, strip cards, subscriptions and check-ins. SquadPass handles the admin so you can focus on your club.') }}
            </p>

            <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                @guest
                    <a href="#signup" class="shrink-0 rounded-xl bg-linear-to-r from-blue-500 to-indigo-600 px-4 py-3 text-sm font-semibold sm:px-6 sm:text-base text-white shadow-lg shadow-blue-500/40 transition hover:from-blue-400 hover:to-indigo-500">
                        {{ __('Get started') }}
                    </a>
                    <livewire:clubs.start-demo />
                @else
                    <a href="{{ route('dashboard') }}" class="rounded-xl px-6 py-3 text-base font-semibold text-zinc-300 ring-1 ring-white/15 transition hover:bg-white/5 hover:text-white">
                        {{ __('Dashboard') }}
                    </a>
                @endguest
            </div>
        </div>

        {{-- Membership pass mockup --}}
        <div class="relative mx-auto w-full max-w-md" aria-hidden="true">
            <div class="absolute inset-0 rotate-6 rounded-3xl bg-linear-to-br from-sky-500/40 to-indigo-600/40 blur-sm"></div>
            <div class="relative -rotate-3 rounded-3xl bg-linear-to-br from-blue-500 via-blue-600 to-indigo-700 p-8 shadow-2xl shadow-blue-950/60 ring-1 ring-white/20 transition duration-500 hover:rotate-0">
                <div class="flex items-start justify-between">
                    <span class="flex size-10 items-center justify-center rounded-xl bg-white/15">
                        <x-app-logo-icon class="size-6 fill-current text-white" />
                    </span>
                    <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold tracking-widest text-white uppercase">{{ __('Member pass') }}</span>
                </div>

                <div class="mt-10">
                    <p class="text-sm text-blue-200">Padel Club Amsterdam</p>
                    <p class="mt-1 text-2xl font-semibold text-white">Alex Janssen</p>
                </div>

                <div class="mt-8 flex items-end justify-between">
                    <div>
                        <p class="text-xs tracking-widest text-blue-200 uppercase">{{ __('Strip card') }}</p>
                        <div class="mt-2 flex gap-1.5">
                            @foreach (range(1, 10) as $strip)
                                <span class="size-2.5 rounded-full {{ $strip <= 7 ? 'bg-white' : 'bg-white/25' }}"></span>
                            @endforeach
                        </div>
                    </div>
                    <div class="grid grid-cols-4 gap-1 rounded-lg bg-white/90 p-1.5">
                        @foreach ([1, 0, 1, 1, 0, 1, 0, 0, 1, 1, 0, 1, 1, 0, 1, 1] as $dot)
                            <span class="size-1.5 rounded-[2px] {{ $dot ? 'bg-blue-900' : 'bg-transparent' }}"></span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section class="py-16 lg:py-24">
        <div class="mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ __('One platform, your whole club') }}</h2>
            <p class="mt-4 text-lg text-zinc-400">{{ __('From the front desk to the locker room, every part of your club runs through SquadPass.') }}</p>
        </div>

        <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['icon' => 'users', 'title' => __('Member management'), 'text' => __('Invite members, track memberships and keep every detail in one place.')],
                ['icon' => 'calendar-days', 'title' => __('Lessons & schedules'), 'text' => __('Plan recurring lessons, manage capacity and let members enroll themselves.')],
                ['icon' => 'ticket', 'title' => __('Strip cards'), 'text' => __('Sell digital strip cards that members redeem with a single check-in.')],
                ['icon' => 'credit-card', 'title' => __('Subscriptions & payments'), 'text' => __('Monthly and yearly plans with automatic credit tracking. Add online payments when you are ready.')],
                ['icon' => 'finger-print', 'title' => __('Check-in desk'), 'text' => __('Check members in at the door and instantly see their coverage.')],
                ['icon' => 'device-phone-mobile', 'title' => __('Member portal'), 'text' => __('Members book lessons, buy cards and follow their visits from any device.')],
            ] as $feature)
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

    <x-marketing.white-label />

    <x-marketing.segments />

    <x-marketing.pricing :addon-prices="$addonPrices" />

    <x-marketing.add-ons :addon-prices="$addonPrices" />

    <x-marketing.faq :items="$faqs" />

    <x-marketing.signup />
</x-marketing.layout>
