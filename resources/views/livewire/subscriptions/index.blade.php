<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Member subscriptions')" :subtitle="$club->name" />

    <x-table-card>
        <flux:table :paginate="$this->subscriptions">
            <flux:table.columns>
                <flux:table.column>{{ __('Member') }}</flux:table.column>
                <flux:table.column>{{ __('Plan') }}</flux:table.column>
                <flux:table.column>{{ __('Started') }}</flux:table.column>
                <flux:table.column>{{ __('Next billing') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->subscriptions as $subscription)
                    <flux:table.row :key="$subscription->id">
                        <flux:table.cell class="sp-strong">
                            @if ($this->membershipIdsByUser[$subscription->user_id] ?? null)
                                <flux:link class="sp-strong" :href="route('clubs.members.show', [$club, $this->membershipIdsByUser[$subscription->user_id]])" wire:navigate variant="ghost">{{ $subscription->user->name }}</flux:link>
                            @else
                                {{ $subscription->user->name }}
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $subscription->plan->name }} · {{ $subscription->plan->formattedPrice() }}</flux:table.cell>
                        <flux:table.cell>{{ $subscription->starts_at->format('d-m-Y') }}</flux:table.cell>
                        <flux:table.cell>{{ $subscription->next_billing_at?->format('d-m-Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
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
</div>
