@props([
    'title',
    'subtitle' => null,
    'eyebrow' => null,
    'actions' => null,
])

{{-- With summary content the header becomes a panel; on its own it is just the page title. --}}
<div {{ $attributes->class(['animate-rise', 'sp-panel p-6 sm:p-7' => $slot->isNotEmpty(), 'px-1' => $slot->isEmpty()]) }}>
    <div class="flex flex-wrap items-start justify-between gap-x-6 gap-y-4">
        <div class="min-w-0">
            @if ($eyebrow)
                <p class="stagger animate-fade text-xs font-semibold tracking-wider text-blue-600 uppercase dark:text-blue-400" style="--d: 80ms">
                    {{ $eyebrow }}
                </p>
            @endif

            <h1 class="mt-1 text-2xl font-semibold tracking-tight text-zinc-900 sm:text-3xl dark:text-white">{{ $title }}</h1>

            @if ($subtitle)
                <p class="stagger animate-rise mt-1.5 max-w-2xl text-sm leading-relaxed text-zinc-500 dark:text-zinc-400" style="--d: 120ms">
                    {{ $subtitle }}
                </p>
            @endif
        </div>

        @if ($actions)
            <div class="stagger animate-rise flex flex-wrap items-center gap-2" style="--d: 160ms">
                {{ $actions }}
            </div>
        @endif
    </div>

    @if ($slot->isNotEmpty())
        <div class="mt-6">
            {{ $slot }}
        </div>
    @endif
</div>
