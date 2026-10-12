@props(['items'])

@php
    $columns = match (count($items)) {
        1 => '',
        2 => 'sm:grid-cols-2 sm:divide-x sm:divide-y-0',
        4 => 'sm:grid-cols-2 sm:divide-x xl:grid-cols-4 [&>*:nth-child(-n+2)]:sm:border-b-0 sm:[&>*:nth-child(2)]:border-s',
        default => 'sm:grid-cols-3 sm:divide-x sm:divide-y-0',
    };
@endphp

<div {{ $attributes->class(['animate-rise grid grid-cols-1 divide-y divide-white/15 overflow-hidden rounded-2xl bg-blue-600', $columns]) }}>
    @foreach ($items as $index => $item)
        <div class="stagger animate-rise p-6" style="--d: {{ 120 + $index * 70 }}ms">
            @if (($item['countUp'] ?? null) !== null)
                <p class="text-3xl leading-none font-semibold text-white tabular-nums"
                   x-data="spCountUp({{ (int) $item['countUp'] }})" x-text="displayed">{{ $item['value'] }}</p>
            @else
                <p class="text-3xl leading-none font-semibold text-white tabular-nums">{{ $item['value'] }}</p>
            @endif

            <p class="mt-3 text-xs font-semibold tracking-wider text-blue-100 uppercase">{{ $item['label'] }}</p>
        </div>
    @endforeach
</div>
