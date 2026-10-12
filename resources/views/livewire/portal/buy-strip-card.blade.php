<div class="flex w-full flex-col gap-6">
    <x-portal.hero
        :eyebrow="__('Pay per visit')"
        :title="__('Buy strip card')"
        :subtitle="__('Pay per visit with a strip card.')"
    >
        <x-slot:actions>
            <flux:button variant="ghost" icon="arrow-left" :href="route('portal.cards')" wire:navigate>{{ __('Back') }}</flux:button>
        </x-slot:actions>
    </x-portal.hero>

    @if ($this->plans->isEmpty())
        <x-portal.empty icon="ticket" :heading="__('No strip cards available')" :text="__('Your clubs do not offer strip cards yet.')" />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->plans as $planIndex => $plan)
                <x-portal.card :delay="min(600, $planIndex * 80)" wire:key="plan-{{ $plan->id }}" class="flex flex-col gap-3 p-6">

                    <div class="relative">
                        <flux:text class="text-sm">{{ $plan->club->name }}</flux:text>
                        <flux:heading class="sp-card-title">{{ $plan->name }}</flux:heading>
                    </div>

                    <div class="relative flex flex-wrap gap-1.5">
                        @foreach (range(1, min($plan->strips, 20)) as $strip)
                            <span class="stagger animate-pop size-2.5 rounded-full bg-blue-500/30 transition duration-300 group-hover:bg-blue-500 dark:bg-blue-400/30 dark:group-hover:bg-blue-400"
                                  style="--d: {{ 200 + $strip * 40 }}ms"></span>
                        @endforeach
                    </div>

                    <flux:text class="relative">
                        {{ trans_choice(':count strip|:count strips', $plan->strips) }}
                        @if ($plan->validity_months)
                            · {{ __('valid :months months', ['months' => $plan->validity_months]) }}
                        @endif
                    </flux:text>

                    <div class="relative mt-auto flex items-center justify-between pt-2">
                        <flux:heading size="lg">{{ $plan->formattedPrice() }}</flux:heading>
                        @if (isset($this->payableClubIds[$plan->club_id]))
                            <flux:button variant="primary" wire:click="buy({{ $plan->id }})">
                                {{ __('Buy') }}
                            </flux:button>
                        @else
                            <flux:tooltip :content="__('This club has not set up online payments yet. Contact the club to buy a strip card.')">
                                <flux:button variant="primary" disabled>{{ __('Buy') }}</flux:button>
                            </flux:tooltip>
                        @endif
                    </div>
                </x-portal.card>
            @endforeach
        </div>
    @endif
</div>
