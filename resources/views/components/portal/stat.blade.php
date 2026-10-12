@props([
    'value',
    'label',
    'delay' => 0,
    'countUp' => null,
])

<div style="--d: {{ $delay }}ms"
     {{ $attributes->class('stagger animate-rise rounded-xl border border-zinc-100 bg-zinc-50/60 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-800/40') }}>
    @if ($countUp !== null)
        <p class="text-xl leading-none font-semibold text-zinc-900 tabular-nums dark:text-white"
           x-data="spCountUp({{ (int) $countUp }})" x-text="displayed">{{ $value }}</p>
    @else
        <p class="truncate text-xl leading-none font-semibold text-zinc-900 dark:text-white">{{ $value }}</p>
    @endif
    <p class="mt-1.5 truncate text-xs font-normal tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ $label }}</p>
</div>
