<div class="flex w-full flex-col gap-8">
    <x-portal.hero :title="__('Settings')" :subtitle="$club->name" />

    @php
        $cardIndex = 0;
    @endphp

    @foreach ($this->sections as $sectionTitle => $cards)
        <section class="flex flex-col gap-3" wire:key="settings-section-{{ $loop->index }}">
            <div class="stagger animate-slide-in flex items-center gap-3" style="--d: {{ $loop->index * 80 }}ms">
                <h2 class="text-sm font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ $sectionTitle }}</h2>
                <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800"></span>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($cards as $card)
                    <x-portal.card :delay="min(500, $cardIndex++ * 60)" wire:key="settings-card-{{ $card['key'] }}" data-test="settings-card-{{ $card['key'] }}">
                        <a href="{{ $card['url'] }}" wire:navigate class="flex h-full flex-col">
                            <div class="flex flex-1 flex-col gap-3 p-5">
                                <div>
                                    <h3 class="text-base leading-tight font-semibold text-zinc-900 transition group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">{{ $card['title'] }}</h3>
                                    <p class="sp-card-subtitle mt-1">{{ $card['status'] }}</p>
                                </div>
                                <flux:text class="text-sm leading-relaxed">{{ $card['description'] }}</flux:text>
                            </div>

                            <div class="flex items-center justify-between border-t border-zinc-100 px-5 py-3 text-sm font-medium text-blue-600 dark:border-white/5 dark:text-blue-400">
                                <span>{{ __('Manage') }}</span>
                                <span aria-hidden="true" class="transition duration-200 group-hover:translate-x-1">&rarr;</span>
                            </div>
                        </a>
                    </x-portal.card>
                @endforeach
            </div>
        </section>
    @endforeach
</div>
