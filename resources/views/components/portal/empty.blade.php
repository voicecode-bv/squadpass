@props([
    'icon' => 'sparkles',
    'heading',
    'text' => null,
])

<div {{ $attributes->class('sp-panel animate-rise flex flex-col items-center px-8 py-14 text-center') }}>
    <span class="animate-float flex size-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
        <flux:icon :icon="$icon" class="size-6" />
    </span>

    <h2 class="sp-card-title stagger animate-rise mt-5 text-zinc-900 dark:text-white" style="--d: 80ms">{{ $heading }}</h2>

    @if ($text)
        <p class="stagger animate-rise mt-1.5 max-w-md text-sm leading-relaxed text-zinc-500 dark:text-zinc-400" style="--d: 120ms">{{ $text }}</p>
    @endif

    @if ($slot->isNotEmpty())
        <div class="stagger animate-rise mt-6" style="--d: 160ms">
            {{ $slot }}
        </div>
    @endif
</div>
