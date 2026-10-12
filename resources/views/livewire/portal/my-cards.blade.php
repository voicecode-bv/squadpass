<div class="flex w-full flex-col gap-6">
    @php($usableCards = $this->cards->filter->isUsable())

    <x-portal.hero
        :eyebrow="__('Strip cards')"
        :title="__('My strip cards')"
        :subtitle="__('Pay per visit with a strip card.')"
    >
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('portal.cards.buy')" wire:navigate
                        >
                {{ __('Buy strip card') }}
            </flux:button>
        </x-slot:actions>

        @if ($this->cards->isNotEmpty())
            <div class="grid gap-3 sm:grid-cols-3">
                <x-portal.stat :count-up="$usableCards->count()" :value="$usableCards->count()" :label="__('Usable cards')" :delay="260" />
                <x-portal.stat :count-up="$usableCards->sum('strips_remaining')" :value="$usableCards->sum('strips_remaining')" :label="__('Strips left')" :delay="320" />
                <x-portal.stat :count-up="$this->cards->pluck('club_id')->unique()->count()" :value="$this->cards->pluck('club_id')->unique()->count()" :label="__('Clubs')" :delay="380" />
            </div>
        @endif
    </x-portal.hero>

    @if ($this->cards->isEmpty())
        <x-portal.empty icon="ticket" :heading="__('No strip cards yet')" :text="__('Buy a strip card to pay per visit.')">
            <flux:button variant="primary" icon="plus" :href="route('portal.cards.buy')" wire:navigate
                        >
                {{ __('Buy strip card') }}
            </flux:button>
        </x-portal.empty>
    @else
        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->cards as $cardIndex => $card)
                @php($usable = $card->isUsable())

                <div wire:key="card-{{ $card->id }}"
                     class="stagger animate-rise group [perspective:1200px]"
                     style="--d: {{ min(600, $cardIndex * 90) }}ms">
                    <div x-data="spTilt(5)"
                         @mousemove="track($event)"
                         @mouseleave="reset()"
                         :style="tiltStyle"
                         class="relative overflow-hidden rounded-2xl p-6 text-white transition-transform duration-200 ease-out will-change-transform {{ $usable ? 'bg-blue-600' : 'bg-zinc-500 dark:bg-zinc-700' }}">

                        <div class="relative flex items-start justify-between">
                            <span class="flex size-10 items-center justify-center rounded-xl bg-white/15 transition duration-300 group-hover:bg-white/25">
                                <x-app-logo-icon class="size-6 fill-current text-white" />
                            </span>
                            <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold tracking-widest uppercase">
                                {{ $usable ? __('Usable') : ($card->strips_remaining === 0 ? __('Empty') : __('Expired')) }}
                            </span>
                        </div>

                        <div class="relative mt-8">
                            <p class="text-sm {{ $usable ? 'text-blue-200' : 'text-zinc-200' }}">{{ $card->club->name }}</p>
                            <p class="sp-card-title mt-1">{{ $card->plan->name }}</p>
                        </div>

                        <div class="relative mt-6 flex items-end justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-xs tracking-widest uppercase {{ $usable ? 'text-blue-200' : 'text-zinc-200' }}">{{ __('Strip card') }}</p>
                                <div class="mt-2 flex max-w-48 flex-wrap gap-1.5">
                                    @foreach (range(1, $card->strips_total) as $strip)
                                        <span class="stagger animate-pop size-2.5 rounded-full {{ $strip <= $card->strips_remaining ? 'bg-white shadow-[0_0_8px_rgba(255,255,255,0.8)]' : 'bg-white/25' }}"
                                              style="--d: {{ 300 + $strip * 45 }}ms"></span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <p class="text-3xl leading-none font-bold tabular-nums">
                                    <span x-data="spCountUp({{ $card->strips_remaining }})" x-text="displayed">{{ $card->strips_remaining }}</span><span class="text-sm font-normal {{ $usable ? 'text-blue-200' : 'text-zinc-200' }}">/{{ $card->strips_total }}</span>
                                </p>
                                <p class="mt-1 text-xs {{ $usable ? 'text-blue-200' : 'text-zinc-200' }}">{{ __('strips left') }}</p>
                            </div>
                        </div>

                        <div class="relative mt-4 h-1.5 overflow-hidden rounded-full bg-white/20">
                            <div class="sp-bar h-full rounded-full bg-white/90"
                                 style="--sp-width: {{ $card->strips_total > 0 ? (int) round($card->strips_remaining / $card->strips_total * 100) : 0 }}%"></div>
                        </div>

                        @if ($card->expires_at)
                            <p class="relative mt-4 text-xs {{ $usable ? 'text-blue-200' : 'text-zinc-200' }}">
                                {{ __('Valid until :date', ['date' => $card->expires_at->format('d-m-Y')]) }}
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
