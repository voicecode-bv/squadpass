<div class="flex w-full flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('My strip cards') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Pay per visit with a strip card.') }}</flux:text>
        </div>
        <flux:button variant="primary" :href="route('portal.cards.buy')" wire:navigate class="border-0 bg-linear-to-r from-blue-500 to-indigo-600 hover:from-blue-400 hover:to-indigo-500">
            {{ __('Buy strip card') }}
        </flux:button>
    </div>

    @if ($this->cards->isEmpty())
        <flux:callout icon="ticket">
            <flux:callout.heading>{{ __('No strip cards yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Buy a strip card to pay per visit.') }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->cards as $card)
                @php($usable = $card->isUsable())
                <div wire:key="card-{{ $card->id }}"
                     class="relative overflow-hidden rounded-3xl p-6 text-white ring-1 ring-white/20 shadow-xl {{ $usable ? 'bg-linear-to-br from-blue-500 via-blue-600 to-indigo-700 shadow-blue-500/25' : 'bg-linear-to-br from-zinc-400 via-zinc-500 to-zinc-600 shadow-zinc-500/20 dark:from-zinc-600 dark:via-zinc-700 dark:to-zinc-800' }}">
                    <div class="pointer-events-none absolute -top-16 -right-10 size-48 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>

                    <div class="relative flex items-start justify-between">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-white/15">
                            <x-app-logo-icon class="size-6 fill-current text-white" />
                        </span>
                        <span class="rounded-full bg-white/15 px-3 py-1 text-xs font-semibold tracking-widest uppercase">
                            {{ $usable ? __('Usable') : ($card->strips_remaining === 0 ? __('Empty') : __('Expired')) }}
                        </span>
                    </div>

                    <div class="relative mt-8">
                        <p class="text-sm {{ $usable ? 'text-blue-200' : 'text-zinc-200' }}">{{ $card->club->name }}</p>
                        <p class="mt-1 text-2xl font-semibold">{{ $card->plan->name }}</p>
                    </div>

                    <div class="relative mt-6 flex items-end justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-xs tracking-widest uppercase {{ $usable ? 'text-blue-200' : 'text-zinc-200' }}">{{ __('Strip card') }}</p>
                            <div class="mt-2 flex max-w-48 flex-wrap gap-1.5">
                                @foreach (range(1, $card->strips_total) as $strip)
                                    <span class="size-2.5 rounded-full {{ $strip <= $card->strips_remaining ? 'bg-white' : 'bg-white/25' }}"></span>
                                @endforeach
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-2xl font-bold leading-none">
                                {{ $card->strips_remaining }}<span class="text-sm font-medium {{ $usable ? 'text-blue-200' : 'text-zinc-200' }}">/{{ $card->strips_total }}</span>
                            </p>
                            <p class="mt-1 text-xs {{ $usable ? 'text-blue-200' : 'text-zinc-200' }}">{{ __('strips left') }}</p>
                        </div>
                    </div>

                    @if ($card->expires_at)
                        <p class="relative mt-4 text-xs {{ $usable ? 'text-blue-200' : 'text-zinc-200' }}">
                            {{ __('Valid until :date', ['date' => $card->expires_at->format('d-m-Y')]) }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
