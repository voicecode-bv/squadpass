@props([
    'title',
    'description',
    'canonical' => url()->current(),
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title }}</title>
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ $canonical }}">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
        <meta property="og:image" content="{{ asset('images/og-image.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ config('app.name') }}: {{ __('Club management, lessons & payments') }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ asset('images/og-image.png') }}">

        {{-- Who makes SquadPass and what it costs, so search engines can describe the product. --}}
        @php
            $pricedPlans = array_values(array_filter(\App\Enums\ClubPlan::cases(), fn (\App\Enums\ClubPlan $plan): bool => $plan->priceCents() !== null));
        @endphp
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Organization',
                        '@id' => route('home').'#organization',
                        'name' => config('app.name'),
                        'url' => route('home'),
                        'logo' => asset('images/logos/squadpass-mark.png'),
                        'email' => config('mail.from.address'),
                    ],
                    [
                        '@type' => 'SoftwareApplication',
                        'name' => config('app.name'),
                        'url' => route('home'),
                        'description' => $description,
                        'applicationCategory' => 'BusinessApplication',
                        'operatingSystem' => 'Web, iOS, Android',
                        'publisher' => ['@id' => route('home').'#organization'],
                        'offers' => array_map(fn (\App\Enums\ClubPlan $plan): array => [
                            '@type' => 'Offer',
                            'name' => $plan->label(),
                            'description' => $plan->description(),
                            'price' => number_format($plan->priceCents() / 100, 2, '.', ''),
                            'priceCurrency' => 'EUR',
                            'url' => route('home').'#pricing',
                        ], $pricedPlans),
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
        </script>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#2563eb">

        {{ $head ?? '' }}

        @include('partials.google-analytics')

        @fonts
        @vite(['resources/css/app.css'])
        @fluxAppearance
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 antialiased">
        {{-- Glow backdrop --}}
        <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute -top-40 left-1/2 h-[32rem] w-[52rem] -translate-x-1/2 rounded-full bg-blue-400/20 dark:bg-blue-600/25 blur-3xl"></div>
            <div class="absolute top-1/3 -left-40 h-96 w-96 rounded-full bg-indigo-400/10 dark:bg-indigo-600/15 blur-3xl"></div>
            <div class="absolute -right-40 bottom-0 h-96 w-96 rounded-full bg-sky-400/10 dark:bg-sky-600/10 blur-3xl"></div>
        </div>

        @if (\App\Models\LaunchOffer::isAvailable())
            {{-- Opening offer, as a full-width bar above the navigation. --}}
            <a href="{{ route('home') }}#opening-offer" class="relative z-20 flex items-center justify-center gap-2 bg-linear-to-r from-blue-600 to-indigo-600 px-6 py-2.5 text-center text-sm font-medium text-white transition hover:from-blue-500 hover:to-indigo-500" data-test="launch-offer-bar">
                <flux:icon.sparkles variant="micro" class="size-4 shrink-0" />
                <span>{{ __('Opening offer: :percent% off for good, :count left', ['percent' => \App\Models\LaunchOffer::discountPercent(), 'count' => trans_choice(':count spot|:count spots', \App\Models\LaunchOffer::spotsLeft())]) }}</span>
                <flux:icon.arrow-right variant="micro" class="size-4 shrink-0" />
            </a>
        @endif

        <div class="relative">
            {{-- Optional full-width backdrop behind the header and the first section (the homepage hero). --}}
            {{ $backdrop ?? '' }}

            <div class="relative mx-auto flex min-h-screen w-full max-w-6xl flex-col px-6 lg:px-8">
                <header class="flex items-center justify-between py-6">
                    <a href="{{ route('home') }}" class="flex items-center">
                        <x-app-logo-lockup class="h-12" />
                    </a>

                    @if (Route::has('login'))
                        <nav class="flex items-center gap-3">
                            <a href="{{ route('features.index') }}" class="hidden rounded-lg px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 transition hover:text-zinc-900 dark:hover:text-white md:inline-block">
                                {{ __('Features') }}
                            </a>
                            <a href="{{ route('features.show', \App\Enums\MarketingFeature::WhiteLabel) }}" class="hidden rounded-lg px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 transition hover:text-zinc-900 dark:hover:text-white lg:inline-block">
                                {{ __('White label') }}
                            </a>
                            <a href="#pricing" class="hidden rounded-lg px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 transition hover:text-zinc-900 dark:hover:text-white sm:inline-block">
                                {{ __('Pricing') }}
                            </a>
                            <a href="#add-ons" class="hidden rounded-lg px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 transition hover:text-zinc-900 dark:hover:text-white sm:inline-block">
                                {{ __('Add-ons') }}
                            </a>
                            <a href="#faq" class="hidden rounded-lg px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 transition hover:text-zinc-900 dark:hover:text-white sm:inline-block">
                                {{ __('FAQ') }}
                            </a>
                            {{-- Follows the system setting until a visitor picks one; Flux stores the choice, shared with the app. --}}
                            <flux:button
                                x-data
                                x-on:click="$flux.dark = ! $flux.dark"
                                variant="ghost"
                                square
                                size="sm"
                                class="shrink-0"
                                :aria-label="__('Switch between light and dark mode')"
                                data-test="appearance-toggle"
                            >
                                <flux:icon.moon variant="mini" class="dark:hidden" />
                                <flux:icon.sun variant="mini" class="hidden dark:block" />
                            </flux:button>
                            @auth
                                <a href="{{ route('dashboard') }}" class="rounded-lg bg-zinc-100 dark:bg-white/10 px-4 py-2 text-sm font-medium text-zinc-900 dark:text-white ring-1 ring-zinc-300 dark:ring-white/15 transition hover:bg-zinc-200 dark:hover:bg-white/20">
                                    {{ __('Dashboard') }}
                                </a>
                            @else
                                <a href="{{ route('login') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-300 transition hover:text-zinc-900 dark:hover:text-white">
                                    {{ __('Log in') }}
                                </a>
                                <a href="#signup" class="rounded-lg bg-zinc-100 dark:bg-white/10 px-4 py-2 text-sm font-medium text-zinc-900 dark:text-white ring-1 ring-zinc-300 dark:ring-white/15 transition hover:bg-zinc-200 dark:hover:bg-white/20">
                                    {{ __('Register') }}
                                </a>
                            @endauth
                        </nav>
                    @endif
                </header>

                <main class="flex flex-1 flex-col">
                    {{ $slot }}
                </main>

                <footer class="border-t border-zinc-200 dark:border-white/10 py-8 text-sm text-zinc-500">
                    <div class="grid gap-8 sm:grid-cols-2">
                        <nav aria-label="{{ __('Features') }}">
                            <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Features') }}</p>
                            <ul class="mt-3 flex flex-col gap-2">
                                @foreach (\App\Enums\MarketingFeature::cases() as $footerFeature)
                                    <li><a href="{{ route('features.show', $footerFeature) }}" class="transition hover:text-zinc-900 dark:hover:text-white">{{ $footerFeature->label() }}</a></li>
                                @endforeach
                            </ul>
                        </nav>
                        <nav aria-label="{{ __('SquadPass for every club') }}">
                            <p class="font-semibold text-zinc-700 dark:text-zinc-300">{{ __('SquadPass for every club') }}</p>
                            <ul class="mt-3 flex flex-col gap-2">
                                @foreach (\App\Enums\ClubSegment::cases() as $footerSegment)
                                    <li><a href="{{ route('segments.show', $footerSegment) }}" class="transition hover:text-zinc-900 dark:hover:text-white">{{ $footerSegment->label() }}</a></li>
                                @endforeach
                            </ul>
                        </nav>
                    </div>
                    <div class="mt-6 flex flex-col items-center gap-2 sm:flex-row sm:justify-between">
                        <span>&copy; {{ now()->year }} {{ config('app.name', 'Laravel') }} · {{ __('Made with :heart in the Netherlands', ['heart' => '❤️']) }}</span>
                        <span class="flex gap-4">
                            <a href="{{ route('privacy') }}" class="transition hover:text-zinc-900 dark:hover:text-white">{{ __('Privacy statement') }}</a>
                            @production
                                <button type="button" data-cookie-settings class="transition hover:text-zinc-900 dark:hover:text-white">{{ __('Cookie settings') }}</button>
                            @endproduction
                        </span>
                        <span>{{ __('Made for clubs that keep moving.') }}</span>
                    </div>
                </footer>
            </div>
        </div>

        @fluxScripts
    </body>
</html>
