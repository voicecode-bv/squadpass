<div class="animate-rise relative overflow-hidden rounded-2xl bg-blue-600 text-white">
    {{-- Decorative rings behind the content; purely visual. --}}
    <div aria-hidden="true" class="pointer-events-none absolute -top-24 -right-24 size-80 rounded-full border-[40px] border-white/5"></div>
    <div aria-hidden="true" class="pointer-events-none absolute -right-6 -bottom-28 size-64 rounded-full border-[28px] border-white/5"></div>

    <div class="relative grid items-center gap-8 p-6 sm:p-8 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold tracking-wider uppercase">
                    <flux:icon.sparkles variant="micro" class="size-3.5" />
                    {{ __('Add-on') }}
                </span>

                @if ($addonKey->logoPath())
                    <img src="{{ asset($addonKey->logoPath()) }}" alt="{{ $this->addon?->name ?? $addonKey->defaultName() }}" class="h-5 w-auto" />
                @endif
            </div>

            <h2 class="stagger animate-rise mt-4 text-2xl font-semibold tracking-tight sm:text-3xl" style="--d: 80ms">
                {{ $addonKey->pitch() }}
            </h2>

            <p class="stagger animate-rise mt-2 max-w-xl text-sm leading-relaxed text-blue-100" style="--d: 120ms">
                {{ $this->addon?->description ?: $addonKey->defaultDescription() }}
            </p>

            <ul class="mt-5 grid gap-2.5">
                @foreach ($addonKey->benefits() as $benefitIndex => $benefit)
                    <li class="stagger animate-rise flex items-start gap-3 text-sm" style="--d: {{ 160 + $benefitIndex * 60 }}ms">
                        <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-white/20">
                            <flux:icon.check variant="micro" class="size-3.5" />
                        </span>
                        {{ $benefit }}
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="stagger animate-pop rounded-2xl bg-white/10 p-5" style="--d: 200ms">
            @if ($this->addon)
                <p class="text-xs font-semibold tracking-wider text-blue-100 uppercase">{{ __('Per month') }}</p>
                <p class="mt-1 text-3xl font-semibold">{{ $this->addon->formattedPrice() }}</p>
                <p class="mt-1 text-xs text-blue-100">{{ __('Cancel monthly · live as soon as you have paid') }}</p>

                @can('update', $club)
                    <button
                        type="button"
                        wire:click="purchase"
                        wire:loading.attr="disabled"
                        class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 disabled:opacity-70"
                    >
                        <flux:icon.loading wire:loading wire:target="purchase" variant="micro" class="size-4" />
                        {{ __('Activate :addon', ['addon' => $this->addon->name]) }}
                        <flux:icon.arrow-right wire:loading.remove wire:target="purchase" variant="micro" class="size-4" />
                    </button>

                    <a href="{{ route('clubs.addons', $club) }}" wire:navigate class="mt-3 block text-center text-xs text-blue-100 underline-offset-4 hover:text-white hover:underline">
                        {{ __('Compare add-ons') }}
                    </a>
                @else
                    <p class="mt-5 text-sm text-blue-50">{{ __('Ask the owner of your club to activate this add-on.') }}</p>
                @endcan
            @else
                <p class="text-sm text-blue-50">{{ __('This add-on is not available right now. Keep an eye on this page.') }}</p>
            @endif
        </div>
    </div>
</div>
