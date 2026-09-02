<div class="flex w-full flex-col gap-6">
    {{-- Header --}}
    <div>
        <flux:button :href="route('clubs.members', $club)" wire:navigate variant="ghost" size="sm" icon="arrow-left">
            {{ __('Back to members') }}
        </flux:button>

        <div class="mt-4 flex items-center gap-4">
            <flux:avatar :src="$membership->user->avatarUrl()" :name="$membership->user->name" :initials="$membership->user->initials()" size="lg" />
            <div>
                <flux:heading size="xl">{{ $membership->user->name }}</flux:heading>
                <flux:text class="mt-0.5">{{ $membership->user->email }}</flux:text>
            </div>
            <div class="ms-auto flex items-center gap-2">
                <flux:badge size="sm" color="zinc">#{{ $membership->membership_number }}</flux:badge>
                <flux:badge size="sm" :color="match($membership->status) {
                    \App\Enums\MembershipStatus::Active => 'green',
                    \App\Enums\MembershipStatus::Paused => 'yellow',
                    \App\Enums\MembershipStatus::Ended => 'zinc',
                }">{{ $membership->status->label() }}</flux:badge>
                @if ($membership->moneybird_contact_id)
                    <flux:badge size="sm" color="green">{{ __('Moneybird') }}</flux:badge>
                @endif
                @if ($club->hasMoneybirdConfigured())
                    <flux:button size="sm" wire:click="syncMoneybird" icon="arrow-path">
                        {{ $membership->moneybird_contact_id ? __('Sync with Moneybird') : __('Link to Moneybird') }}
                    </flux:button>
                @endif
            </div>
        </div>
    </div>

    {{-- Membership details --}}
    <div class="grid gap-6 rounded-xl bg-white p-6 shadow-sm sm:grid-cols-2 lg:grid-cols-4 dark:bg-zinc-900">
        <div>
            <p class="flex items-center gap-1.5 text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">
                <flux:icon.identification variant="micro" class="size-4" /> {{ __('Membership number') }}
            </p>
            <p class="mt-2 font-medium text-zinc-800 dark:text-white">{{ $membership->membership_number }}</p>
        </div>
        <div>
            <p class="flex items-center gap-1.5 text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">
                <flux:icon.calendar variant="micro" class="size-4" /> {{ __('Member since') }}
            </p>
            <p class="mt-2 font-medium text-zinc-800 dark:text-white">{{ $membership->joined_at->format('d-m-Y') }}</p>
        </div>
        <div>
            <p class="flex items-center gap-1.5 text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">
                <flux:icon.envelope variant="micro" class="size-4" /> {{ __('Email') }}
            </p>
            <p class="mt-2 truncate font-medium text-zinc-800 dark:text-white">{{ $membership->user->email }}</p>
        </div>
        <div>
            <p class="flex items-center gap-1.5 text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">
                <flux:icon.x-circle variant="micro" class="size-4" /> {{ __('Ended at') }}
            </p>
            <p class="mt-2 font-medium text-zinc-800 dark:text-white">{{ $membership->ended_at?->format('d-m-Y') ?? '-' }}</p>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <span class="flex size-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                <flux:icon.ticket class="size-5" />
            </span>
            <p class="mt-4 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Strips remaining') }}</p>
            <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $this->stripsRemaining }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <span class="flex size-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                <flux:icon.credit-card class="size-5" />
            </span>
            <p class="mt-4 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Active subscriptions') }}</p>
            <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $this->activeSubscriptionCount }}</p>
        </div>
        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <span class="flex size-10 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400">
                <flux:icon.finger-print class="size-5" />
            </span>
            <p class="mt-4 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Check-ins this month') }}</p>
            <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $this->checkInsThisMonth }}</p>
        </div>
    </div>

    {{-- Subscriptions --}}
    <section class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Subscriptions') }}</flux:heading>

        @if ($this->subscriptions->isEmpty())
            <flux:text>{{ __('No subscriptions.') }}</flux:text>
        @else
            <x-table-card>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="w-2/5">{{ __('Plan') }}</flux:table.column>
                        <flux:table.column>{{ __('Started') }}</flux:table.column>
                        <flux:table.column>{{ __('Next billing') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Status') }}</flux:table.column>
                        @if ($club->hasMoneybirdConfigured())
                            <flux:table.column align="end">{{ __('Moneybird') }}</flux:table.column>
                        @endif
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->subscriptions as $subscription)
                            <flux:table.row :key="'sub-'.$subscription->id">
                                <flux:table.cell variant="strong">{{ $subscription->plan->name }} · {{ $subscription->plan->formattedPrice() }}</flux:table.cell>
                                <flux:table.cell>{{ $subscription->starts_at->format('d-m-Y') }}</flux:table.cell>
                                <flux:table.cell>{{ $subscription->next_billing_at?->format('d-m-Y') ?? '-' }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:badge size="sm" :color="match($subscription->status) {
                                        \App\Enums\SubscriptionStatus::Active => 'green',
                                        \App\Enums\SubscriptionStatus::Pending => 'yellow',
                                        \App\Enums\SubscriptionStatus::PastDue => 'red',
                                        default => 'zinc',
                                    }">{{ $subscription->status->label() }}</flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-table-card>
        @endif
    </section>

    {{-- Strip cards --}}
    <section class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Strip cards') }}</flux:heading>

        @if ($this->stripCards->isEmpty())
            <flux:text>{{ __('No strip cards.') }}</flux:text>
        @else
            <x-table-card>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="w-2/5">{{ __('Plan') }}</flux:table.column>
                        <flux:table.column>{{ __('Strips') }}</flux:table.column>
                        <flux:table.column>{{ __('Purchased') }}</flux:table.column>
                        <flux:table.column>{{ __('Valid until') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Status') }}</flux:table.column>
                        @if ($club->hasMoneybirdConfigured())
                            <flux:table.column align="end">{{ __('Moneybird') }}</flux:table.column>
                        @endif
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->stripCards as $card)
                            <flux:table.row :key="'card-'.$card->id">
                                <flux:table.cell variant="strong">{{ $card->plan->name }}</flux:table.cell>
                                <flux:table.cell>{{ $card->strips_remaining }} / {{ $card->strips_total }}</flux:table.cell>
                                <flux:table.cell>{{ $card->purchased_at->format('d-m-Y') }}</flux:table.cell>
                                <flux:table.cell>{{ $card->expires_at?->format('d-m-Y') ?? '-' }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:badge size="sm" :color="$card->isUsable() ? 'green' : 'zinc'">
                                        {{ $card->isUsable() ? __('Usable') : ($card->strips_remaining === 0 ? __('Empty') : __('Expired')) }}
                                    </flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-table-card>
        @endif
    </section>

    {{-- Payments --}}
    <section class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Recent payments') }}</flux:heading>

        @if ($this->payments->isEmpty())
            <flux:text>{{ __('No payments.') }}</flux:text>
        @else
            @php($purposeLabels = [
                'strip_card' => __('Strip card'),
                'subscription_first' => __('Subscription'),
                'subscription_recurring' => __('Subscription'),
            ])
            <x-table-card>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="w-2/5">{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Description') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Status') }}</flux:table.column>
                        @if ($club->hasMoneybirdConfigured())
                            <flux:table.column align="end">{{ __('Moneybird') }}</flux:table.column>
                        @endif
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->payments as $payment)
                            <flux:table.row :key="'payment-'.$payment->id">
                                <flux:table.cell variant="strong">{{ ($payment->paid_at ?? $payment->created_at)->timezone($club->timezone)->isoFormat('D MMM YYYY, HH:mm') }}</flux:table.cell>
                                <flux:table.cell>{{ $purposeLabels[$payment->purpose()] ?? __('Payment') }}</flux:table.cell>
                                <flux:table.cell align="end">&euro; {{ number_format($payment->amount_cents / 100, 2, ',', '.') }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:badge size="sm" :color="match($payment->status) {
                                        \App\Enums\PaymentStatus::Paid => 'green',
                                        \App\Enums\PaymentStatus::Open => 'yellow',
                                        \App\Enums\PaymentStatus::Refunded => 'zinc',
                                        default => 'red',
                                    }">{{ $payment->status->label() }}</flux:badge>
                                </flux:table.cell>
                                @if ($club->hasMoneybirdConfigured())
                                    <flux:table.cell align="end">
                                        @if ($payment->moneybird_credit_invoice_id)
                                            <flux:link :href="$club->moneybirdInvoiceUrl($payment->moneybird_credit_invoice_id)" target="_blank" class="text-xs">{{ __('Credit invoice') }}</flux:link>
                                        @elseif ($payment->moneybird_invoice_id)
                                            <flux:link :href="$club->moneybirdInvoiceUrl($payment->moneybird_invoice_id)" target="_blank" class="text-xs">{{ __('Invoice') }}</flux:link>
                                        @else
                                            <flux:text class="text-xs">—</flux:text>
                                        @endif
                                    </flux:table.cell>
                                @endif
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-table-card>
        @endif
    </section>

    {{-- Check-ins --}}
    <section class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Recent check-ins') }}</flux:heading>

        @if ($this->checkIns->isEmpty())
            <flux:text>{{ __('No check-ins yet.') }}</flux:text>
        @else
            <x-table-card>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="w-2/5">{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Lesson') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Coverage') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->checkIns as $checkIn)
                            <flux:table.row :key="'checkin-'.$checkIn->id">
                                <flux:table.cell variant="strong">{{ $checkIn->checked_in_at->timezone($club->timezone)->isoFormat('D MMM YYYY, HH:mm') }}</flux:table.cell>
                                <flux:table.cell>{{ $checkIn->occurrence?->lesson->name ?? '-' }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:badge size="sm">{{ $checkIn->coverageLabel() }}</flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-table-card>
        @endif
    </section>
</div>
