@props([
    'sidebar' => false,
    'club' => null,
])

@php($clubLogoUrl = $club?->logoUrl())
@php($clubDarkLogoUrl = $club?->darkLogoUrl())

{{-- A plain anchor rather than flux:brand: that component clamps its logo slot to
     h-10 with overflow-hidden, which crops the lockup. A club with an uploaded
     logo shows that logo instead, with a small "Powered by" line under it. --}}
<a {{ $attributes->class('flex min-w-0 shrink-0 items-center') }}>
    @if ($clubLogoUrl)
        <span class="flex min-w-0 flex-col items-start gap-1">
            {{-- The dark variant is swapped in on dark surfaces, like the SquadPass lockup. --}}
            <img src="{{ $clubLogoUrl }}" alt="{{ $club->name }}"
                 class="{{ $sidebar ? 'h-11' : 'h-10' }} w-auto max-w-full object-contain object-left dark:hidden" />
            <img src="{{ $clubDarkLogoUrl }}" alt="" aria-hidden="true"
                 class="{{ $sidebar ? 'h-11' : 'h-10' }} hidden w-auto max-w-full object-contain object-left dark:block" />
            <span class="text-[10px] leading-none text-ink-muted">{{ __('Powered by :app', ['app' => config('app.name', 'SquadPass')]) }}</span>
        </span>
    @else
        <x-app-logo-lockup class="{{ $sidebar ? 'h-11' : 'h-10' }}" />
    @endif
</a>
