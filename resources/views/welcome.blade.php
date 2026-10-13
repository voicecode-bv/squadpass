<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ __('Welcome') }} - {{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#2563eb">

        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-zinc-950 text-zinc-100 antialiased">
        {{-- Glow backdrop --}}
        <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -top-40 left-1/2 h-[32rem] w-[52rem] -translate-x-1/2 rounded-full bg-blue-600/25 blur-3xl"></div>
            <div class="absolute top-1/3 -left-40 h-96 w-96 rounded-full bg-indigo-600/15 blur-3xl"></div>
            <div class="absolute -right-40 bottom-0 h-96 w-96 rounded-full bg-sky-600/10 blur-3xl"></div>
        </div>

        <div class="relative mx-auto flex min-h-screen w-full max-w-6xl flex-col px-6 lg:px-8">
            <header class="flex items-center justify-between py-6">
                <a href="{{ route('home') }}" class="flex items-center">
                    <x-app-logo-lockup class="h-12" />
                </a>

                @if (Route::has('login'))
                    <nav class="flex items-center gap-3">
                        <a href="#pricing" class="hidden rounded-lg px-4 py-2 text-sm font-medium text-zinc-300 transition hover:text-white sm:inline-block">
                            {{ __('Pricing') }}
                        </a>
                        <a href="#add-ons" class="hidden rounded-lg px-4 py-2 text-sm font-medium text-zinc-300 transition hover:text-white sm:inline-block">
                            {{ __('Add-ons') }}
                        </a>
                        @auth
                            <a href="{{ route('dashboard') }}" class="rounded-lg bg-white/10 px-4 py-2 text-sm font-medium text-white ring-1 ring-white/15 transition hover:bg-white/20">
                                {{ __('Dashboard') }}
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-zinc-300 transition hover:text-white">
                                {{ __('Log in') }}
                            </a>
                            <a href="#signup" class="rounded-lg bg-white/10 px-4 py-2 text-sm font-medium text-white ring-1 ring-white/15 transition hover:bg-white/20">
                                {{ __('Register') }}
                            </a>
                        @endauth
                    </nav>
                @endif
            </header>

            <main class="flex flex-1 flex-col">
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

                        <div class="flex flex-wrap items-center gap-4">
                            @guest
                                <a href="#signup" class="rounded-xl bg-linear-to-r from-blue-500 to-indigo-600 px-6 py-3 text-base font-semibold text-white shadow-lg shadow-blue-500/40 transition hover:from-blue-400 hover:to-indigo-500">
                                    {{ __('Get started') }}
                                </a>
                            @endguest
                            <a href="{{ route('login') }}" class="rounded-xl px-6 py-3 text-base font-semibold text-zinc-300 ring-1 ring-white/15 transition hover:bg-white/5 hover:text-white">
                                {{ __('Log in') }}
                            </a>
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


                {{-- Pricing --}}
                @php($addonPrices = \App\Models\Addon::landingPrices())
                @php($lowestAddonPrice = $addonPrices === [] ? null : min($addonPrices))
                <section id="pricing" class="scroll-mt-8 py-16 lg:py-24">
                    <div class="mx-auto max-w-2xl text-center">
                        <span class="inline-flex items-center gap-2 rounded-full bg-blue-500/10 px-4 py-1.5 text-sm font-medium text-blue-300 ring-1 ring-blue-400/30">
                            <flux:icon.banknotes variant="micro" class="size-4" />
                            {{ __('Pricing') }}
                        </span>
                        <h2 class="mt-6 text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ __('One price per month, based on your club size') }}</h2>
                        <p class="mt-4 text-lg text-zinc-400">{{ __('Every plan includes all features for your whole club. You only choose which add-ons you switch on.') }}</p>
                    </div>

                    <div class="mt-14 grid items-stretch gap-6 lg:grid-cols-3">
                        @foreach (collect(\App\Enums\ClubPlan::cases())->map(fn (\App\Enums\ClubPlan $clubPlan) => [
                            'title' => $clubPlan->label(),
                            'text' => $clubPlan->description(),
                            'price_cents' => $clubPlan->priceCents(),
                            'featured' => $clubPlan === \App\Enums\ClubPlan::Starter,
                        ]) as $plan)
                            <div class="relative flex h-full flex-col overflow-hidden rounded-3xl p-8 {{ $plan['featured'] ? 'bg-linear-to-br from-blue-600/20 to-indigo-700/10 ring-1 ring-blue-400/40' : 'bg-white/5 ring-1 ring-white/10' }}">
                                @if ($plan['featured'])
                                    <div class="pointer-events-none absolute -top-20 -right-20 size-56 rounded-full bg-blue-500/20 blur-3xl" aria-hidden="true"></div>
                                @endif

                                <h3 class="relative text-lg font-semibold text-white">{{ $plan['title'] }}</h3>
                                <p class="relative mt-2 text-sm leading-relaxed text-zinc-400">{{ $plan['text'] }}</p>

                                <p class="relative mt-8 flex flex-wrap items-baseline gap-x-2">
                                    @if ($plan['price_cents'] !== null)
                                        <span class="text-4xl font-bold tracking-tight text-white">{{ \App\Models\Addon::formatCents($plan['price_cents']) }}</span>
                                        <span class="text-sm text-zinc-400">/ {{ __('month') }}</span>
                                    @else
                                        <span class="text-4xl font-bold tracking-tight text-white">{{ __('On request') }}</span>
                                    @endif
                                </p>

                                @if ($plan['price_cents'] !== null)
                                    <a href="#signup" class="relative mt-8 rounded-xl px-6 py-3 text-center text-base font-semibold transition {{ $plan['featured'] ? 'bg-linear-to-r from-blue-500 to-indigo-600 text-white shadow-lg shadow-blue-500/40 hover:from-blue-400 hover:to-indigo-500' : 'text-zinc-300 ring-1 ring-white/15 hover:bg-white/5 hover:text-white' }}">
                                        {{ __('Get started') }}
                                    </a>
                                @else
                                    <a href="mailto:{{ config('mail.from.address') }}" class="relative mt-8 rounded-xl px-6 py-3 text-center text-base font-semibold text-zinc-300 ring-1 ring-white/15 transition hover:bg-white/5 hover:text-white">
                                        {{ __('Contact us') }}
                                    </a>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-10 rounded-3xl bg-white/5 p-8 ring-1 ring-white/10">
                        <h3 class="text-lg font-semibold text-white">{{ __('Included in every plan') }}</h3>
                        <ul class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ([
                                __('Unlimited trainers and staff'),
                                __('Lessons, schedules and enrollments'),
                                __('Strip cards and subscriptions'),
                                __('Check-in desk for the front door'),
                                __('Member portal on every device'),
                                __('Support by ticket included'),
                            ] as $included)
                                <li class="flex items-start gap-3 text-zinc-300">
                                    <flux:icon.check-circle variant="micro" class="mt-0.5 size-5 shrink-0 text-blue-300" />
                                    <span>{{ $included }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <p class="mt-6 border-t border-white/10 pt-6 text-sm text-zinc-400">
                            @if ($lowestAddonPrice !== null)
                                {{ __('Add-ons such as online payments, invoicing and your own mobile app are optional and billed separately, from :price per month.', ['price' => \App\Models\Addon::formatCents($lowestAddonPrice)]) }}
                            @else
                                {{ __('Add-ons such as online payments, invoicing and your own mobile app are optional and billed separately.') }}
                            @endif
                            <a href="#add-ons" class="ml-1 inline-flex items-center gap-1.5 font-semibold text-blue-300 transition hover:text-white">
                                {{ __('Compare add-ons') }}
                                <flux:icon.arrow-right variant="micro" class="size-4" />
                            </a>
                        </p>
                    </div>
                </section>

                {{-- Add-ons --}}
                <section id="add-ons" class="scroll-mt-8 py-16 lg:py-24">
                    <div class="mx-auto max-w-2xl text-center">
                        <span class="inline-flex items-center gap-2 rounded-full bg-blue-500/10 px-4 py-1.5 text-sm font-medium text-blue-300 ring-1 ring-blue-400/30">
                            <flux:icon.puzzle-piece variant="micro" class="size-4" />
                            {{ __('Add-ons & integrations') }}
                        </span>
                        <h2 class="mt-6 text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ __('Connect SquadPass with the tools that make your club stronger') }}</h2>
                        <p class="mt-4 text-lg text-zinc-400">{{ __('Your plan covers the essentials, so switch on extras the moment you need them. Every add-on is billed per month and can be cancelled monthly.') }}</p>
                    </div>

                    <div class="mt-14 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                        @foreach ([
                            [
                                'key' => \App\Enums\AddonKey::Mollie,
                                'category' => __('Payments & direct debit'),
                                'title' => __('Online payments with Mollie'),
                                'text' => __('Members pay for strip cards and subscriptions online through your own Mollie account. Recurring charges run automatically via direct debit.'),
                                'badges' => [__('iDEAL & cards'), __('SEPA direct debit'), __('Failed charge alerts')],
                            ],
                            [
                                'key' => \App\Enums\AddonKey::Moneybird,
                                'category' => __('Accounting & invoicing'),
                                'title' => __('Invoicing with Moneybird'),
                                'text' => __('Every member becomes a Moneybird contact and every paid payment turns into a draft sales invoice, ready to send from your own administration.'),
                                'badges' => [__('Contact sync'), __('Draft invoices'), __('Member numbers as customer IDs')],
                            ],
                            [
                                'key' => \App\Enums\AddonKey::MobileApp,
                                'category' => __('Your own app'),
                                'title' => __('Branded mobile app'),
                                'text' => __('Your club in the App Store and Google Play, with your own logo and colours. Members book lessons, check their strip card and check in from their phone.'),
                                'badges' => [__('iOS & Android'), __('Your logo and colours'), __('Push notifications')],
                            ],
                        ] as $item)
                            @php($priceCents = $addonPrices[$item['key']->value] ?? null)
                            <div class="group relative flex flex-col gap-5 overflow-hidden rounded-3xl bg-white/5 p-8 ring-1 ring-white/10 transition hover:bg-white/10 hover:ring-blue-400/40">
                                <div class="pointer-events-none absolute -top-16 -right-16 size-48 rounded-full bg-blue-500/10 blur-2xl transition group-hover:bg-blue-500/20" aria-hidden="true"></div>

                                <div class="relative flex items-start justify-between gap-4">
                                    @if ($item['key']->logoPath() !== null)
                                        <img src="{{ asset($item['key']->logoPath()) }}" alt="{{ $item['title'] }}" class="h-7 w-auto" loading="lazy" />
                                    @else
                                        <span class="flex size-9 items-center justify-center rounded-xl bg-blue-500/15 text-blue-300 ring-1 ring-blue-400/30">
                                            <flux:icon :icon="$item['key']->icon()" class="size-5" />
                                        </span>
                                    @endif
                                    <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-medium tracking-wide text-zinc-300 ring-1 ring-white/10">{{ $item['category'] }}</span>
                                </div>

                                <div class="relative">
                                    <h3 class="text-xl font-semibold text-white">{{ $item['title'] }}</h3>
                                    <p class="mt-2 leading-relaxed text-zinc-400">{{ $item['text'] }}</p>
                                </div>

                                <div class="relative flex flex-col items-start gap-2">
                                    @foreach ($item['badges'] as $badge)
                                        <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-500/10 px-2.5 py-1 text-xs font-medium text-blue-200 ring-1 ring-blue-400/20">
                                            <flux:icon.check variant="micro" class="size-3.5" />
                                            {{ $badge }}
                                        </span>
                                    @endforeach
                                </div>

                                <div class="relative mt-auto flex items-end justify-between gap-4 border-t border-white/10 pt-5">
                                    <div>
                                        @if ($priceCents !== null)
                                            <p class="text-2xl font-semibold text-white">{{ \App\Models\Addon::formatCents($priceCents) }}<span class="text-sm font-normal text-zinc-400"> / {{ __('month') }}</span></p>
                                            <p class="mt-1 text-xs text-zinc-500">{{ __('Cancel monthly, no minimum term') }}</p>
                                        @else
                                            <p class="text-sm font-medium text-zinc-300">{{ __('Coming soon') }}</p>
                                        @endif
                                    </div>
                                    <a href="#signup" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-300 transition hover:text-white">
                                        {{ __('Activate in your club') }}
                                        <flux:icon.arrow-right variant="micro" class="size-4" />
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-10 grid gap-6 rounded-3xl bg-white/5 p-8 ring-1 ring-white/10 sm:grid-cols-3">
                        @foreach ([
                            ['icon' => 'cursor-arrow-rays', 'title' => __('Activate in one click'), 'text' => __('Buy an add-on from your club settings and pay securely via Mollie. It is live the moment the payment succeeds.')],
                            ['icon' => 'arrow-path', 'title' => __('Cancel monthly'), 'text' => __('No long contracts. Cancel whenever you like and keep using the add-on until the end of the paid month.')],
                            ['icon' => 'sparkles', 'title' => __('More on the way'), 'text' => __('We keep adding integrations. Missing one your club relies on? Let us know.')],
                        ] as $point)
                            <div class="flex gap-4">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/15 text-blue-300 ring-1 ring-blue-400/30">
                                    <flux:icon :icon="$point['icon']" class="size-5" />
                                </span>
                                <div>
                                    <h3 class="font-semibold text-white">{{ $point['title'] }}</h3>
                                    <p class="mt-1 text-sm leading-relaxed text-zinc-400">{{ $point['text'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- CTA: signup with account and club in one step --}}
                <section id="signup" class="scroll-mt-8 pb-24">
                    <div class="relative overflow-hidden rounded-3xl bg-linear-to-br from-blue-600 to-indigo-700 px-6 py-12 shadow-2xl shadow-blue-950/50 sm:px-12 lg:py-16">
                        <div class="pointer-events-none absolute -top-24 left-1/2 h-64 w-[36rem] -translate-x-1/2 rounded-full bg-white/15 blur-3xl" aria-hidden="true"></div>
                        @guest
                            <div class="relative grid items-center gap-10 lg:grid-cols-2 lg:gap-16">
                                <div class="text-center lg:text-left">
                                    <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ __('Ready to get started?') }}</h2>
                                    <p class="mt-4 text-lg text-blue-100">{{ __('Create your club in minutes and invite your members today.') }}</p>
                                </div>

                                <livewire:clubs.signup />
                            </div>
                        @else
                            <div class="relative text-center">
                                <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">{{ __('Ready to get started?') }}</h2>
                                <p class="mx-auto mt-4 max-w-xl text-lg text-blue-100">{{ __('Create your club in minutes and invite your members today.') }}</p>
                                <a href="{{ route('dashboard') }}" class="mt-8 inline-block rounded-xl bg-white px-6 py-3 text-base font-semibold text-blue-700 shadow-lg transition hover:bg-blue-50">
                                    {{ __('Dashboard') }}
                                </a>
                            </div>
                        @endguest
                    </div>
                </section>
            </main>

            <footer class="flex items-center justify-between border-t border-white/10 py-8 text-sm text-zinc-500">
                <span>&copy; {{ now()->year }} {{ config('app.name', 'Laravel') }}</span>
                <span>{{ __('Made for clubs that keep moving.') }}</span>
            </footer>
        </div>

        @fluxScripts
    </body>
</html>
