@props([
    'tone' => 'info',
    'heading',
    'href' => null,
    'action' => null,
])

{{--
    A notice that must not be missed, in the same flat language as the KPI band:
    a solid brand-blue panel (red for something urgent) with white text and one
    white action on the right. No icon; the colour and the action carry the message.
    Use the `actions` slot for buttons instead of a link.
--}}
@php
    $isUrgent = $tone === 'danger';
@endphp

<div {{ $attributes->class([
    'animate-rise flex flex-col gap-4 rounded-2xl p-5 text-white sm:flex-row sm:items-center sm:justify-between sm:p-6',
    'bg-blue-600' => ! $isUrgent,
    'bg-red-600' => $isUrgent,
]) }} role="status">
    <div class="min-w-0">
        <p class="text-lg leading-tight font-semibold">{{ $heading }}</p>
        @if ($slot->isNotEmpty())
            <div @class(['mt-1 text-sm', 'text-blue-100' => ! $isUrgent, 'text-red-100' => $isUrgent])>{{ $slot }}</div>
        @endif
    </div>

    @if ($href && $action)
        <a href="{{ $href }}" wire:navigate @class([
            'group inline-flex shrink-0 items-center gap-2 self-start rounded-xl bg-white px-4 py-2.5 text-sm font-semibold transition hover:bg-blue-50 sm:self-center',
            'text-blue-700' => ! $isUrgent,
            'text-red-700 hover:bg-red-50' => $isUrgent,
        ])>
            <span>{{ $action }}</span>
            <span aria-hidden="true" class="transition duration-200 group-hover:translate-x-1">&rarr;</span>
        </a>
    @endif

    @isset($actions)
        <div class="flex shrink-0 gap-2 self-start sm:self-center">{{ $actions }}</div>
    @endisset
</div>
