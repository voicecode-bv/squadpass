@props([
    'values' => [],
    'labels' => [],
    'display' => null,
    'svgClass' => 'h-40',
    'labelClass' => '',
])

@php
    $values = array_values($values);
    $count = count($values);
    $max = max(1, $count > 0 ? max($values) : 0);
    $step = $count > 1 ? 100 / ($count - 1) : 100;
    $points = collect($values)
        ->map(fn ($value, $index) => round($index * $step, 2).','.round(34 - ($value / $max) * 30, 2))
        ->implode(' ');
    $gradientId = 'area-gradient-'.uniqid();
@endphp

<div {{ $attributes }}>
    <svg viewBox="0 0 100 36" preserveAspectRatio="none" class="w-full {{ $svgClass }} text-blue-500" fill="none" aria-hidden="true">
        <defs>
            <linearGradient id="{{ $gradientId }}" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0" stop-color="currentColor" stop-opacity="0.2" />
                <stop offset="1" stop-color="currentColor" stop-opacity="0" />
            </linearGradient>
        </defs>
        @if ($count > 1)
            <polygon points="0,36 {{ $points }} 100,36" fill="url(#{{ $gradientId }})" />
            <polyline points="{{ $points }}" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
        @endif
    </svg>

    @if ($labels !== [])
        <div class="mt-2 flex justify-between text-center text-xs {{ $labelClass }}">
            @foreach ($labels as $index => $label)
                <span>
                    <span class="block font-semibold text-zinc-600 dark:text-zinc-300">{{ $display[$index] ?? $values[$index] ?? 0 }}</span>
                    <span class="text-zinc-400 dark:text-zinc-500">{{ $label }}</span>
                </span>
            @endforeach
        </div>
    @endif
</div>
