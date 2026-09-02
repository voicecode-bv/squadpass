<div class="flex w-full flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Member subscriptions') }}</flux:heading>
        <flux:text class="mt-1">{{ $club->name }}</flux:text>
    </div>

    @if ($this->failedCharges->isNotEmpty())
        <section class="flex flex-col gap-4">
            <div>
                <flux:heading size="lg">{{ __('Failed charges') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Recurring charges that could not be collected.') }}</flux:text>
            </div>

            <x-table-card>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                        <flux:table.column>{{ __('Member') }}</flux:table.column>
                        <flux:table.column>{{ __('Plan') }}</flux:table.column>
                        <flux:table.column>{{ __('Amount') }}</flux:table.column>
                        <flux:table.column>{{ __('Payment') }}</flux:table.column>
                        <flux:table.column>{{ __('Subscription') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->failedCharges as $payment)
                            <flux:table.row :key="'failed-'.$payment->id">
                                <flux:table.cell variant="strong">{{ $payment->updated_at->timezone($club->timezone)->isoFormat('D MMM YYYY, HH:mm') }}</flux:table.cell>
                                <flux:table.cell><x-user-chip :user="$payment->user" /></flux:table.cell>
                                <flux:table.cell>{{ $payment->payable?->plan?->name ?? '?' }}</flux:table.cell>
                                <flux:table.cell>&euro; {{ number_format($payment->amount_cents / 100, 2, ',', '.') }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm" color="red">{{ $payment->status->label() }}</flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    @if ($payment->payable)
                                        <flux:badge size="sm" :color="$payment->payable->status === \App\Enums\SubscriptionStatus::PastDue ? 'red' : 'zinc'">
                                            {{ $payment->payable->status->label() }}
                                        </flux:badge>
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-table-card>
        </section>
    @endif

    <x-table-card>
    <flux:table :paginate="$this->subscriptions">
        <flux:table.columns>
            <flux:table.column>{{ __('Member') }}</flux:table.column>
            <flux:table.column>{{ __('Plan') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Started') }}</flux:table.column>
            <flux:table.column>{{ __('Next billing') }}</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->subscriptions as $subscription)
                <flux:table.row :key="$subscription->id">
                    <flux:table.cell variant="strong"><x-user-chip :user="$subscription->user" /></flux:table.cell>
                    <flux:table.cell>{{ $subscription->plan->name }} · {{ $subscription->plan->formattedPrice() }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="match($subscription->status) {
                            \App\Enums\SubscriptionStatus::Active => 'green',
                            \App\Enums\SubscriptionStatus::Pending => 'yellow',
                            \App\Enums\SubscriptionStatus::PastDue => 'red',
                            default => 'zinc',
                        }">{{ $subscription->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $subscription->starts_at->format('d-m-Y') }}</flux:table.cell>
                    <flux:table.cell>{{ $subscription->next_billing_at?->format('d-m-Y') ?? '—' }}</flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
    </x-table-card>
</div>
