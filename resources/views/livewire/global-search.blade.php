<div class="relative w-full max-w-md min-w-0"
     x-data="{ open: false }"
     @keydown.escape.window="open = false"
     @keydown.window.meta.k.prevent="$refs.input.focus()"
     @keydown.window.ctrl.k.prevent="$refs.input.focus()"
     @click.outside="open = false">
    <flux:input
        x-ref="input"
        wire:model.live.debounce.300ms="query"
        @focus="open = true"
        icon="magnifying-glass"
        :placeholder="__('Search...')"
        :label="__('Search')"
        label:sr-only
        clearable
    />

    <div x-cloak x-show="open && $wire.query.trim().length >= 2"
         x-transition.opacity.duration.150ms
         class="sp-panel absolute z-30 mt-2 max-h-[26rem] w-full overflow-y-auto py-2 shadow-lg">
        @forelse ($this->groups as $group)
            <p class="px-4 pt-2 pb-1 text-xs font-semibold tracking-wider text-zinc-400 uppercase dark:text-zinc-500">
                {{ $group['heading'] }}
            </p>

            @foreach ($group['items'] as $item)
                <a href="{{ $item['url'] }}" wire:navigate
                   @click="open = false"
                   wire:key="result-{{ $item['key'] }}"
                   class="block px-4 py-2 transition hover:bg-zinc-100 dark:hover:bg-white/5">
                    <span class="block truncate text-sm text-zinc-900 dark:text-white">{{ $item['label'] }}</span>
                    <span class="sp-card-subtitle block truncate text-zinc-500 dark:text-zinc-400">{{ $item['meta'] }}</span>
                </a>
            @endforeach
        @empty
            <p class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No results found.') }}</p>
        @endforelse
    </div>
</div>
