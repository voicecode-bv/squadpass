@props([
    'values' => [],
    'labels' => [],
    'titles' => [],
    'display' => null,
])

@php
    $values = array_values($values);
    $max = max(1, $values === [] ? 0 : max($values));
@endphp

<div {{ $attributes }}>
    <div class="flex h-40 items-end gap-1.5 sm:gap-3">
        @foreach ($values as $index => $value)
            <div class="flex h-full flex-1 flex-col items-center justify-end" title="{{ $titles[$index] ?? $value }}">
                <span class="mb-1 text-[10px] font-normal whitespace-nowrap text-zinc-500 dark:text-zinc-400">{{ $display[$index] ?? $value }}</span>
                {{-- Bars scale to 85% so the value label above always fits inside the chart area. --}}
                <div class="w-full shrink-0 rounded-t bg-blue-500 transition hover:bg-blue-600"
                     style="height: {{ $value > 0 ? max(round($value / $max * 85, 1), 2) : 1 }}%"></div>
            </div>
        @endforeach
    </div>

    @if ($labels !== [])
        <div class="mt-2 flex gap-1.5 text-xs text-zinc-400 sm:gap-3 dark:text-zinc-500">
            @foreach ($labels as $label)
                <span class="flex-1 text-center">{{ $label }}</span>
            @endforeach
        </div>
    @endif
</div>
