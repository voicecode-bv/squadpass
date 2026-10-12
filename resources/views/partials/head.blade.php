<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

{{-- Club pages sit under clubs/{club}, so the bound model names the club in the title. --}}
@php
    $routeClub = request()->route('club');

    $titleSegments = array_filter([
        filled($title ?? null) ? __($title) : null,
        $routeClub instanceof \App\Models\Club ? $routeClub->name : null,
        config('app.name', 'SquadPass'),
    ], fn ($segment) => filled($segment));
@endphp

<title>
    {{ implode(' - ', $titleSegments) }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<meta name="theme-color" content="#2563eb">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
