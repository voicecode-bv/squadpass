{{-- Mark plus wordmark. The white variant is swapped in on dark surfaces. --}}
<span {{ $attributes->class('inline-flex items-center') }}>
    <img src="{{ asset('images/logos/squadpass-lockup.svg') }}" alt="{{ config('app.name', 'SquadPass') }}" class="h-full w-auto dark:hidden" />
    <img src="{{ asset('images/logos/squadpass-lockup-white.svg') }}" alt="" aria-hidden="true" class="hidden h-full w-auto dark:block" />
</span>
