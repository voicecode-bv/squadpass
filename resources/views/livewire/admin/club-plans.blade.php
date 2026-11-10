<div class="flex w-full flex-col gap-8">
    <x-portal.hero :title="__('SquadPass plans')" :subtitle="__('Set the monthly price and member limit of each plan. A new price applies to new subscriptions only; a new member limit applies to every club on the plan right away.')" />

    <x-table-card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Plan') }}</flux:table.column>
                <flux:table.column>{{ __('Member limit') }}</flux:table.column>
                <flux:table.column>{{ __('Price per month') }}</flux:table.column>
                <flux:table.column>{{ __('Active subscriptions') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($plans as $plan)
                    <flux:table.row :key="$plan->value">
                        <flux:table.cell class="sp-strong">
                            {{ $plan->label() }}
                            <flux:text class="text-xs">{{ $plan->value }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell>{{ $plan->memberLimit() === null ? __('Unlimited') : number_format($plan->memberLimit(), 0, ',', '.') }}</flux:table.cell>
                        <flux:table.cell>{{ $plan->priceCents() === null ? __('On request') : \App\Models\Addon::formatCents($plan->priceCents()) }}</flux:table.cell>
                        <flux:table.cell>{{ $this->activeSubscriptionCounts[$plan->value] ?? 0 }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="edit('{{ $plan->value }}')">{{ __('Edit') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-table-card>

    <section class="sp-panel p-5" data-test="launch-offer">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="sp-card-title">{{ __('Opening offer') }}</h2>
                <p class="sp-card-subtitle">
                    {{ $launchOffer['is_active'] ? __('Active') : __('Inactive') }}
                    · {{ __(':taken of :spots spots taken', ['taken' => $this->foundingSpotsTaken, 'spots' => $launchOffer['spots']]) }}
                </p>
            </div>
            <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="editOffer">{{ __('Edit') }}</flux:button>
        </div>

        <flux:text class="mt-4">
            {{ __('The first :spots clubs that take out a plan get :percent% off every plan for as long as they stay a customer. A club claims its spot when it chooses a plan and gives it back if the first payment fails.', ['spots' => $launchOffer['spots'], 'percent' => $launchOffer['discount_percent']]) }}
        </flux:text>
    </section>

    <div>
        <flux:heading size="lg">{{ __('Club subscriptions') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Every SquadPass plan taken out by a club.') }}</flux:text>
    </div>

    @if ($this->subscriptions->isEmpty())
        <flux:callout icon="credit-card">
            <flux:callout.heading>{{ __('No SquadPass subscriptions yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Subscriptions appear here as soon as a club chooses a plan.') }}</flux:callout.text>
        </flux:callout>
    @else
        <x-table-card>
            <flux:table :paginate="$this->subscriptions">
                <flux:table.columns>
                    <flux:table.column>{{ __('Club') }}</flux:table.column>
                    <flux:table.column>{{ __('Plan') }}</flux:table.column>
                    <flux:table.column>{{ __('Price per month') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Next billing') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->subscriptions as $subscription)
                        <flux:table.row :key="$subscription->id">
                            <flux:table.cell class="sp-strong">
                                <flux:link :href="route('clubs.plan', $subscription->club)" wire:navigate>{{ $subscription->club->name }}</flux:link>
                                @if ($subscription->club->isFoundingMember())
                                    <flux:text class="text-xs">{{ __('Opening price (:percent% off)', ['percent' => $subscription->club->founding_discount_percent]) }}</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $subscription->plan->label() }}</flux:table.cell>
                            <flux:table.cell>{{ $subscription->isBilledManually() ? __('Billed manually') : $subscription->formattedPrice() }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="match($subscription->status) {
                                    \App\Enums\SubscriptionStatus::Active => 'green',
                                    \App\Enums\SubscriptionStatus::Pending => 'yellow',
                                    \App\Enums\SubscriptionStatus::PastDue => 'red',
                                    default => 'zinc',
                                }">{{ $subscription->status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($subscription->next_billing_at)
                                    {{ $subscription->next_billing_at->format('d-m-Y') }}
                                @elseif ($subscription->ends_at)
                                    {{ __('ends :date', ['date' => $subscription->ends_at->format('d-m-Y')]) }}
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-table-card>
    @endif

    <flux:modal name="edit-launch-offer" class="md:w-96">
        <form wire:submit="saveOffer" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit opening offer') }}</flux:heading>
                <flux:text class="mt-2">{{ __('A new discount applies to clubs that claim a spot from now on. Clubs with the opening price keep the discount they claimed.') }}</flux:text>
            </div>

            <flux:switch wire:model="offer_active" :label="__('Offer active')" :description="__('Shown on the website and applied when a club chooses a plan.')" />
            <flux:input wire:model="offer_discount_percent" :label="__('Discount (%)')" type="number" step="1" min="1" max="100" />
            <flux:input wire:model="offer_spots" :label="__('Number of spots')" :description="__('Number of clubs that can claim the opening price.')" type="number" step="1" min="0" />

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="edit-club-plan" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit plan') }}</flux:heading>
            </div>

            <flux:input wire:model="member_limit" :label="__('Member limit')" :description="__('Maximum number of active members. Leave empty for unlimited.')" type="number" step="1" min="1" />
            <flux:input wire:model="price" :label="__('Price per month (€)')" :description="__('Leave empty to price the plan on request: clubs cannot take it out themselves and a superadmin activates it.')" type="number" step="0.01" min="0" />

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
