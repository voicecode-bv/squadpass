<div class="flex w-full flex-col gap-8">
    <x-portal.hero :title="__('Add-ons')" :subtitle="__('Extend :club with extra features. Add-ons are billed monthly via direct debit and can be cancelled monthly.', ['club' => $club->name])" />

    @if ($this->addons->isEmpty())
        <flux:callout icon="puzzle-piece">
            <flux:callout.heading>{{ __('No add-ons available') }}</flux:callout.heading>
            <flux:callout.text>{{ __('There are no add-ons for sale at the moment.') }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($this->addons as $addon)
                @php($subscription = $this->subscriptions->get($addon->id))
                @php($status = $subscription?->status)
                @php($unlocked = $subscription?->isCurrent() ?? false)
                <div wire:key="addon-{{ $addon->id }}" class="sp-panel flex flex-col gap-4 p-6">
                    <div class="flex items-start justify-between gap-4">
                        <span class="flex size-11 items-center justify-center rounded-xl {{ $unlocked ? 'bg-green-50 text-green-600 dark:bg-green-500/10 dark:text-green-400' : 'bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400' }}">
                            <flux:icon :name="$addon->key->icon()" class="size-5" />
                        </span>
                        @if ($subscription)
                            <flux:badge size="sm" :color="match($status) {
                                $statuses::Active => 'green',
                                $statuses::Pending => 'yellow',
                                $statuses::PastDue => 'red',
                                $statuses::Cancelled => $unlocked ? 'amber' : 'zinc',
                                default => 'zinc',
                            }">{{ $status->label() }}</flux:badge>
                        @endif
                    </div>

                    <div>
                        <flux:heading class="sp-card-title">{{ $addon->name }}</flux:heading>
                        @if ($addon->description)
                            <flux:text class="sp-card-subtitle mt-1">{{ $addon->description }}</flux:text>
                        @endif
                    </div>

                    <div class="mt-auto flex items-end justify-between gap-4">
                        <div>
                            <flux:heading size="lg">{{ $subscription?->isCurrent() ? $subscription->formattedPrice() : $addon->formattedPrice() }}</flux:heading>
                            <flux:text class="text-xs">
                                @if ($status === $statuses::Active && $subscription->next_billing_at)
                                    {{ __('next billing :date', ['date' => $subscription->next_billing_at->format('d-m-Y')]) }}
                                @elseif ($status === $statuses::Cancelled && $unlocked)
                                    {{ __('active until :date', ['date' => $subscription->ends_at->format('d-m-Y')]) }}
                                @elseif ($status === $statuses::PastDue)
                                    {{ __('The last direct debit failed. Retry the payment to reactivate the add-on.') }}
                                @elseif ($status === $statuses::Pending)
                                    {{ __('Waiting for payment') }}
                                @else
                                    {{ __('Per month') }}
                                @endif
                            </flux:text>
                        </div>

                        <div class="flex gap-2">
                            @if ($unlocked && $addon->key === \App\Enums\AddonKey::Moneybird)
                                <flux:button size="sm" variant="ghost" icon="cog-6-tooth" :href="route('clubs.moneybird', $club)" wire:navigate>{{ __('Set up') }}</flux:button>
                            @elseif ($unlocked && $addon->key === \App\Enums\AddonKey::Mollie)
                                <flux:button size="sm" variant="ghost" icon="cog-6-tooth" :href="route('clubs.settings', $club)" wire:navigate>{{ __('Set up') }}</flux:button>
                            @endif
                            @if ($status === $statuses::Active)
                                <flux:button size="sm" wire:click="cancel({{ $subscription->id }})" wire:confirm="{{ __('Cancel this add-on? It stays active until the end of the paid month.') }}">{{ __('Cancel') }}</flux:button>
                            @elseif ($status === $statuses::PastDue)
                                <flux:button size="sm" variant="ghost" wire:click="cancel({{ $subscription->id }})">{{ __('Cancel') }}</flux:button>
                                <flux:button size="sm" variant="primary" wire:click="retryPayment({{ $subscription->id }})">{{ __('Retry payment') }}</flux:button>
                            @elseif ($status === $statuses::Cancelled && $unlocked)
                                <flux:button size="sm" variant="primary" wire:click="resume({{ $subscription->id }})">{{ __('Resume') }}</flux:button>
                            @elseif ($status === $statuses::Pending)
                                <flux:button size="sm" variant="primary" wire:click="purchase({{ $addon->id }})" disabled>{{ __('Waiting for payment') }}</flux:button>
                            @else
                                <flux:button size="sm" variant="primary" icon="shopping-cart" wire:click="purchase({{ $addon->id }})">{{ __('Buy') }}</flux:button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($this->payments->isNotEmpty())
        <div>
            <flux:heading class="sp-card-title">{{ __('Payment history') }}</flux:heading>
        </div>

        <x-table-card>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Add-on') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->payments as $payment)
                        <flux:table.row :key="$payment->id">
                            <flux:table.cell>{{ $payment->created_at->format('d-m-Y') }}</flux:table.cell>
                            <flux:table.cell class="sp-strong">{{ $payment->subscription->addon->name }}</flux:table.cell>
                            <flux:table.cell>{{ $payment->formattedAmount() }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="match($payment->status) {
                                    \App\Enums\PaymentStatus::Paid => 'green',
                                    \App\Enums\PaymentStatus::Open => 'yellow',
                                    \App\Enums\PaymentStatus::Failed, \App\Enums\PaymentStatus::Expired => 'red',
                                    default => 'zinc',
                                }">{{ $payment->status->label() }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-table-card>
    @endif
</div>
