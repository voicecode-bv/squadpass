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
        <meta name="twitter:card" content="summary">

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#2563eb">

        {{ $head ?? '' }}

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
                        <a href="#faq" class="hidden rounded-lg px-4 py-2 text-sm font-medium text-zinc-300 transition hover:text-white sm:inline-block">
                            {{ __('FAQ') }}
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
                {{ $slot }}
            </main>

            <footer class="border-t border-white/10 py-8 text-sm text-zinc-500">
                <nav class="flex flex-wrap justify-center gap-x-6 gap-y-2 sm:justify-start" aria-label="{{ __('SquadPass for every club') }}">
                    @foreach (\App\Enums\ClubSegment::cases() as $footerSegment)
                        <a href="{{ route('segments.show', $footerSegment) }}" class="transition hover:text-white">{{ $footerSegment->label() }}</a>
                    @endforeach
                </nav>
                <div class="mt-6 flex flex-col items-center gap-2 sm:flex-row sm:justify-between">
                    <span>&copy; {{ now()->year }} {{ config('app.name', 'Laravel') }} · {{ __('Made with :heart in the Netherlands', ['heart' => '❤️']) }}</span>
                    <span>{{ __('Made for clubs that keep moving.') }}</span>
                </div>
            </footer>
        </div>

        @fluxScripts
    </body>
</html>
