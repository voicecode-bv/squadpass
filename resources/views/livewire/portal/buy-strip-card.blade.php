<div class="flex w-full flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Buy strip card') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Pay per visit with a strip card.') }}</flux:text>
    </div>

    @if ($this->plans->isEmpty())
        <flux:callout icon="ticket">
            <flux:callout.heading>{{ __('No strip cards available') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Your clubs do not offer strip cards yet.') }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->plans as $plan)
                <div wire:key="plan-{{ $plan->id }}"
                     class="group flex flex-col gap-3 rounded-2xl bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-blue-500/10 dark:bg-zinc-900">
                    <span class="flex size-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600 transition group-hover:bg-linear-to-br group-hover:from-blue-500 group-hover:to-indigo-600 group-hover:text-white dark:bg-blue-500/10 dark:text-blue-400">
                        <flux:icon.ticket class="size-5" />
                    </span>
                    <div>
                        <flux:text class="text-sm">{{ $plan->club->name }}</flux:text>
                        <flux:heading size="lg">{{ $plan->name }}</flux:heading>
                    </div>
                    <flux:text>
                        {{ trans_choice(':count strip|:count strips', $plan->strips) }}
                        @if ($plan->validity_months)
                            · {{ __('valid :months months', ['months' => $plan->validity_months]) }}
                        @endif
                    </flux:text>
                    <div class="mt-auto flex items-center justify-between">
                        <flux:heading size="lg">{{ $plan->formattedPrice() }}</flux:heading>
                        <flux:button variant="primary" wire:click="buy({{ $plan->id }})" class="border-0 bg-linear-to-r from-blue-500 to-indigo-600 hover:from-blue-400 hover:to-indigo-500">
                            {{ __('Buy') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
