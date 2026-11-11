@php
    $subscription = $this->subscription;
    $status = $subscription?->status;
    $current = $subscription?->isCurrent() ? $subscription : null;
    $canSubscribe = $subscription === null || ! $subscription->blocksNewSubscription();
    $canSwitch = $status === $statuses::Active && ! $subscription->isBilledManually();
    $memberLimit = $club->memberLimit();
    $hasPromotionalPrice = $club->hasPromotionalPrice();
    $promotionAvailable = ! $hasPromotionalPrice && $canSubscribe && \App\Models\Promotion::isAvailable();
    $promotionPercent = \App\Models\Promotion::discountPercent();
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

    @cannot('manageBilling', $club)
        <flux:callout icon="information-circle">
            <flux:callout.heading>{{ __('Not available in the demo') }}</flux:callout.heading>
            <flux:callout.text>{{ __('In a demo club you cannot choose a plan or buy add-ons. Every add-on is already switched on, so you can try them all.') }}</flux:callout.text>
        </flux:callout>
    @else

    @if ($hasPromotionalPrice)
        <x-portal.notice :heading="__('Your promotional price: :percent% off', ['percent' => $club->promotion_discount_percent])" data-test="founding-notice">
            {{ __(':club took out a plan during a promotion. You keep this discount on every plan for as long as you stay a customer.', ['club' => $club->name]) }}
        </x-portal.notice>
    @elseif ($promotionAvailable)
        <x-portal.notice :heading="__(':name: :percent% off for good', ['name' => \App\Models\Promotion::name(), 'percent' => $promotionPercent])" data-test="promotion-notice">
            {{ __('Choose a plan now and keep :percent% off for as long as you stay a customer.', ['percent' => $promotionPercent]) }}
        </x-portal.notice>
    @endif

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
        <flux:callout icon="clock" variant="warning">
            <flux:callout.heading>{{ __('Payment pending') }}</flux:callout.heading>
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
        @php
            $usagePercentage = $memberLimit ? min(100, (int) round($this->activeMemberCount / max(1, $memberLimit) * 100)) : null;
        @endphp

        <section class="sp-panel animate-rise overflow-hidden" data-test="current-plan">
            <div class="flex flex-wrap items-start justify-between gap-4 p-6">
                <div>
                    <p class="sp-card-subtitle uppercase tracking-wide">{{ __('Your plan') }}</p>
                    <div class="mt-1 flex items-center gap-3">
                        <h2 class="sp-card-title">{{ $current->plan->label() }}</h2>
                        <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                    </div>
                </div>

                @unless ($current->isBilledManually())
                    <div class="text-end">
                        <p class="text-2xl font-semibold text-zinc-900 tabular-nums dark:text-white">{{ $current->formattedPrice() }}</p>
                        <p class="sp-muted text-xs">{{ __('Per month') }}</p>
                    </div>
                @endunless
            </div>

            <dl class="grid gap-6 border-t border-zinc-100 p-6 sm:grid-cols-3 dark:border-white/5">
                <div>
                    <dt class="sp-muted text-xs tracking-wide uppercase">{{ __('Next billing') }}</dt>
                    <dd class="sp-strong mt-1">
                        {{ $current->isBilledManually() ? __('Invoiced separately') : ($current->next_billing_at?->format('d-m-Y') ?? '-') }}
                    </dd>
                </div>
                <div>
                    <dt class="sp-muted text-xs tracking-wide uppercase">{{ __('Active since') }}</dt>
                    <dd class="sp-strong mt-1">{{ $current->starts_at->format('d-m-Y') }}</dd>
                </div>
                <div>
                    <dt class="sp-muted text-xs tracking-wide uppercase">{{ __('Active members') }}</dt>
                    <dd class="sp-strong mt-1 tabular-nums">
                        {{ number_format($this->activeMemberCount, 0, ',', '.') }}
                        <span class="sp-muted font-normal">/ {{ $memberLimit === null ? __('Unlimited') : number_format($memberLimit, 0, ',', '.') }}</span>
                    </dd>
                    @if ($usagePercentage !== null)
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                            <div class="sp-bar h-full rounded-full {{ $usagePercentage >= 90 ? 'bg-amber-500' : 'bg-blue-600' }}" style="--sp-width: {{ $usagePercentage }}%"></div>
                        </div>
                    @endif
                </div>
            </dl>

            @unless ($current->isBilledManually())
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-100 px-6 py-3 dark:border-white/5">
                    <flux:text class="text-xs">{{ __('Cancel monthly; your plan stays active until the end of the paid month.') }}</flux:text>
                    <flux:button size="sm" variant="ghost" wire:click="cancel" wire:confirm="{{ __('Cancel your plan? It stays active until the end of the paid month. After that you cannot add members.') }}">{{ __('Cancel plan') }}</flux:button>
                </div>
            @endunless
        </section>
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
                        @if ($plan->isSelfService() && ($hasPromotionalPrice || $promotionAvailable))
                            <flux:heading size="lg">{{ \App\Models\Addon::formatCents($hasPromotionalPrice ? $club->planPriceCents($plan) : \App\Models\Promotion::applyDiscount($plan->priceCents(), $promotionPercent)) }}</flux:heading>
                            <flux:text class="text-xs">{{ __('Per month') }}</flux:text>
                            <flux:text class="text-xs whitespace-nowrap line-through">{{ \App\Models\Addon::formatCents($plan->priceCents()) }}</flux:text>
                        @elseif ($plan->isSelfService())
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
    @endcannot
</div>
