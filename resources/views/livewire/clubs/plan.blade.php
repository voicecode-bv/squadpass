@php
    $subscription = $this->subscription;
    $status = $subscription?->status;
    $current = $subscription?->isCurrent() ? $subscription : null;
    $canSubscribe = $subscription === null || ! $subscription->blocksNewSubscription();
    $canSwitch = $status === $statuses::Active && ! $subscription->isBilledManually();
    $memberLimit = $club->memberLimit();
@endphp

<div class="flex w-full flex-col gap-8">
    <x-portal.hero :title="__('SquadPass plan')" :subtitle="__('Your plan determines how many active members :club can have. Plans are billed monthly via direct debit and can be cancelled monthly.', ['club' => $club->name])" />

    <x-portal.kpi-band :items="[
        ['label' => __('Plan'), 'value' => $current?->plan->label() ?? ($club->isOnTrial() ? __('Trial') : __('No plan'))],
        ['label' => __('Active members'), 'value' => $memberLimit === null
            ? number_format($this->activeMemberCount, 0, ',', '.')
            : number_format($this->activeMemberCount, 0, ',', '.').' / '.number_format($memberLimit, 0, ',', '.')],
        ['label' => __('Status'), 'value' => $status?->label() ?? ($club->isOnTrial() ? __('Trial') : __('Inactive'))],
    ]" />

    @if ($status === $statuses::PastDue)
        <flux:callout icon="exclamation-triangle" variant="danger">
            <flux:callout.heading>{{ __('Payment overdue') }}</flux:callout.heading>
            <flux:callout.text>{{ __('The last direct debit failed. Until it is paid you cannot add members. Existing members keep their access.') }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" variant="primary" wire:click="retryPayment">{{ __('Retry payment') }}</flux:button>
                <flux:button size="sm" variant="ghost" wire:click="cancel">{{ __('Cancel') }}</flux:button>
            </x-slot>
        </flux:callout>
    @elseif ($status === $statuses::Pending)
        <flux:callout icon="clock">
            <flux:callout.text>{{ __('Waiting for your first payment. Your plan is activated as soon as it is paid.') }}</flux:callout.text>
        </flux:callout>
    @elseif ($current && $status === $statuses::Cancelled)
        <flux:callout icon="information-circle" variant="warning">
            <flux:callout.text>{{ __('Your plan is cancelled and stays active until :date.', ['date' => $current->ends_at->format('d-m-Y')]) }}</flux:callout.text>
            @unless ($current->isBilledManually())
                <x-slot name="actions">
                    <flux:button size="sm" variant="primary" wire:click="resume">{{ __('Resume') }}</flux:button>
                </x-slot>
            @endunless
        </flux:callout>
    @elseif ($current)
        <flux:callout icon="check-circle" variant="success">
            <flux:callout.text>
                @if ($current->isBilledManually())
                    {{ __('Your plan is active and invoiced separately.') }}
                @else
                    {{ __(':price per month, next billing :date.', ['price' => $current->formattedPrice(), 'date' => $current->next_billing_at?->format('d-m-Y')]) }}
                @endif
            </flux:callout.text>
            @unless ($current->isBilledManually())
                <x-slot name="actions">
                    <flux:button size="sm" variant="ghost" wire:click="cancel" wire:confirm="{{ __('Cancel your plan? It stays active until the end of the paid month. After that you cannot add members.') }}">{{ __('Cancel') }}</flux:button>
                </x-slot>
            @endunless
        </flux:callout>
    @elseif ($club->isOnTrial())
        <flux:callout icon="sparkles">
            <flux:callout.heading>{{ __('Your trial runs until :date', ['date' => $club->trial_ends_at->format('d-m-Y')]) }}</flux:callout.heading>
            <flux:callout.text>{{ __('During the trial you can add up to :limit members. Choose a plan to keep adding members after the trial. Choosing a plan now ends the trial and starts your first month today.', ['limit' => number_format(\App\Enums\ClubPlan::TRIAL_MEMBER_LIMIT, 0, ',', '.')]) }}</flux:callout.text>
        </flux:callout>
    @else
        <flux:callout icon="lock-closed" variant="warning">
            <flux:callout.heading>{{ __('Your trial has ended') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Choose a plan to add members again. Existing members keep their access.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        @foreach ($plans as $plan)
            @php
                $isCurrentPlan = $current?->plan === $plan;
                $limit = $plan->memberLimit();
                $fits = $limit === null || $this->activeMemberCount <= $limit;
            @endphp
            <div wire:key="plan-{{ $plan->value }}" class="sp-panel flex flex-col gap-4 p-6">
                <div class="flex items-start justify-between gap-4">
                    <flux:heading class="sp-card-title">{{ $plan->label() }}</flux:heading>
                    @if ($isCurrentPlan)
                        <flux:badge size="sm" color="green">{{ __('Current plan') }}</flux:badge>
                    @endif
                </div>

                <flux:text>{{ $plan->description() }}</flux:text>

                <div class="mt-auto flex items-end justify-between gap-4">
                    <div>
                        @if ($plan->isSelfService())
                            <flux:heading size="lg">{{ \App\Models\Addon::formatCents($plan->priceCents()) }}</flux:heading>
                            <flux:text class="text-xs">{{ __('Per month') }}</flux:text>
                        @else
                            <flux:heading size="lg">{{ __('On request') }}</flux:heading>
                        @endif
                    </div>

                    <div class="flex gap-2">
                        @if ($isCurrentPlan)
                            {{-- Nothing to do: this is the plan in force. --}}
                        @elseif (! $plan->isSelfService())
                            @can('manage-platform')
                                @if ($canSubscribe)
                                    <flux:button size="sm" variant="primary" wire:click="activateManually('{{ $plan->value }}')" wire:confirm="{{ __('Activate this plan for :club? It is invoiced outside SquadPass.', ['club' => $club->name]) }}">{{ __('Activate manually') }}</flux:button>
                                @endif
                            @else
                                <flux:button size="sm" :href="'mailto:'.config('mail.from.address')">{{ __('Contact us') }}</flux:button>
                            @endcan
                        @elseif (! $fits)
                            <flux:text class="text-xs">{{ __('Too many active members') }}</flux:text>
                        @elseif ($canSwitch)
                            <flux:button size="sm" wire:click="changePlan('{{ $plan->value }}')" wire:confirm="{{ __('Switch to this plan? The new member limit applies right away, the new price from the next billing date.') }}">{{ __('Switch') }}</flux:button>
                        @elseif ($canSubscribe)
                            <flux:button size="sm" variant="primary" wire:click="subscribe('{{ $plan->value }}')">{{ __('Choose plan') }}</flux:button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($this->payments->isNotEmpty())
        <div>
            <flux:heading class="sp-card-title">{{ __('Payment history') }}</flux:heading>
        </div>

        <x-table-card>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Plan') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->payments as $payment)
                        <flux:table.row :key="$payment->id">
                            <flux:table.cell>{{ $payment->created_at->format('d-m-Y') }}</flux:table.cell>
                            <flux:table.cell class="sp-strong">{{ $payment->subscription->plan->label() }}</flux:table.cell>
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
