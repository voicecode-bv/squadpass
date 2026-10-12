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
                @can('impersonate', $membership)
                    <form method="POST" action="{{ route('clubs.members.impersonate', [$club, $membership]) }}">
                        @csrf
                        <flux:button type="submit" size="sm" icon="eye">
                            {{ __('View as member') }}
                        </flux:button>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    {{-- Membership details --}}
    <div class="sp-panel animate-rise grid gap-6 p-6 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">{{ __('Membership number') }}</p>
            <p class="mt-2 font-normal text-zinc-800 dark:text-white">{{ $membership->membership_number }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">{{ __('Member since') }}</p>
            <p class="mt-2 font-normal text-zinc-800 dark:text-white">{{ $membership->joined_at->format('d-m-Y') }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">{{ __('Email') }}</p>
            <p class="mt-2 truncate font-normal text-zinc-800 dark:text-white">{{ $membership->user->email }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">{{ __('Ended at') }}</p>
            <p class="mt-2 font-normal text-zinc-800 dark:text-white">{{ $membership->ended_at?->format('d-m-Y') ?? '-' }}</p>
        </div>
    </div>

    {{-- KPI band --}}
    <x-portal.kpi-band :items="[
        ['label' => __('Strips remaining').' · '.__('Right now'), 'value' => $this->stripsRemaining, 'countUp' => $this->stripsRemaining],
        ['label' => __('Active subscriptions').' · '.__('Right now'), 'value' => $this->activeSubscriptionCount, 'countUp' => $this->activeSubscriptionCount],
        ['label' => __('Check-ins').' · '.__('This month'), 'value' => $this->checkInsThisMonth, 'countUp' => $this->checkInsThisMonth],
    ]" />

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
                                <flux:table.cell class="sp-strong">{{ $subscription->plan->name }} · {{ $subscription->plan->formattedPrice() }}</flux:table.cell>
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
                                <flux:table.cell class="sp-strong">{{ $card->plan->name }}</flux:table.cell>
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
                                <flux:table.cell class="sp-strong">{{ ($payment->paid_at ?? $payment->created_at)->timezone($club->timezone)->isoFormat('D MMM YYYY, HH:mm') }}</flux:table.cell>
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

    {{-- Forms --}}
    <section class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Forms') }}</flux:heading>

        @if ($this->forms->isEmpty())
            <flux:text>{{ __('This club has no published forms.') }}</flux:text>
        @else
            <x-table-card>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column class="w-2/5">{{ __('Form') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column align="end"></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->forms as $form)
                            @php($submission = $this->formSubmissions->get($form->id))
                            <flux:table.row :key="'form-'.$form->id">
                                <flux:table.cell class="sp-strong">{{ $form->name }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($submission && $submission->reopened_at)
                                        <flux:badge size="sm" color="amber">{{ __('Reopened') }}</flux:badge>
                                    @elseif ($submission)
                                        <flux:badge size="sm" color="green">
                                            {{ __('Filled in on :date', ['date' => $submission->submitted_at->timezone($club->timezone)->isoFormat('D MMM YYYY')]) }}
                                        </flux:badge>
                                    @else
                                        <flux:badge size="sm" color="amber">{{ __('Not filled in yet') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end">
                                    @if ($submission)
                                        <flux:button variant="ghost" size="sm" :href="route('clubs.forms.submissions.show', [$club, $form, $submission])" wire:navigate>
                                            {{ __('View') }}
                                        </flux:button>
                                    @endif
                                </flux:table.cell>
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
                                <flux:table.cell class="sp-strong">{{ $checkIn->checked_in_at->timezone($club->timezone)->isoFormat('D MMM YYYY, HH:mm') }}</flux:table.cell>
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
