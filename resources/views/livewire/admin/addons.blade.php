<div class="flex w-full flex-col gap-8">
    <x-portal.hero :title="__('Add-ons')" :subtitle="__('Manage the add-ons clubs can buy and set their monthly price. Price changes apply to new purchases only.')" />

    <x-table-card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Add-on') }}</flux:table.column>
                <flux:table.column>{{ __('Price per month') }}</flux:table.column>
                <flux:table.column>{{ __('Active subscriptions') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->addons as $addon)
                    <flux:table.row :key="$addon->id">
                        <flux:table.cell class="sp-strong">
                            <div class="flex items-center gap-3">
                                <flux:icon :name="$addon->key->icon()" class="size-5 text-zinc-400" />
                                <div>
                                    {{ $addon->name }}
                                    <flux:text class="text-xs">{{ $addon->key->value }}</flux:text>
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>{{ $addon->formattedPrice() }}</flux:table.cell>
                        <flux:table.cell>{{ $addon->active_subscriptions_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$addon->is_active ? 'green' : 'zinc'">
                                {{ $addon->is_active ? __('For sale') : __('Not for sale') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="edit({{ $addon->id }})">{{ __('Edit') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-table-card>

    <div>
        <flux:heading size="lg">{{ __('Club subscriptions') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Every add-on subscription taken out by a club.') }}</flux:text>
    </div>

    @if ($this->subscriptions->isEmpty())
        <flux:callout icon="puzzle-piece">
            <flux:callout.heading>{{ __('No add-on subscriptions yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Subscriptions appear here as soon as a club buys an add-on.') }}</flux:callout.text>
        </flux:callout>
    @else
        <x-table-card>
            <flux:table :paginate="$this->subscriptions">
                <flux:table.columns>
                    <flux:table.column>{{ __('Club') }}</flux:table.column>
                    <flux:table.column>{{ __('Add-on') }}</flux:table.column>
                    <flux:table.column>{{ __('Price per month') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Next billing') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->subscriptions as $subscription)
                        <flux:table.row :key="$subscription->id">
                            <flux:table.cell class="sp-strong">
                                <flux:link :href="route('clubs.addons', $subscription->club)" wire:navigate>{{ $subscription->club->name }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ $subscription->addon->name }}</flux:table.cell>
                            <flux:table.cell>{{ $subscription->formattedPrice() }}</flux:table.cell>
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

    <flux:modal name="edit-addon" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit add-on') }}</flux:heading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
            <flux:input wire:model="price" :label="__('Price per month (€)')" type="number" step="0.01" min="0" required />
            <flux:switch wire:model="is_active" :label="__('For sale')" :description="__('Clubs can only buy add-ons that are for sale. Existing subscriptions keep running.')" />

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
