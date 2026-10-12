@props([
    'sidebar' => false,
])

{{-- A plain anchor rather than flux:brand: that component clamps its logo slot to
     h-10 with overflow-hidden, which crops the lockup. --}}
<a {{ $attributes->class('flex min-w-0 shrink-0 items-center') }}>
    <x-app-logo-lockup class="{{ $sidebar ? 'h-11' : 'h-10' }}" />
</a>
