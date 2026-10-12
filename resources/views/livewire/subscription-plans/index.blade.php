<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Subscription plans')" :subtitle="$club->name">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="newPlan">{{ __('New plan') }}</flux:button>
        </x-slot:actions>
    </x-portal.hero>

    @if ($this->plans->isEmpty())
        <flux:callout icon="credit-card">
            <flux:callout.heading>{{ __('No subscription plans yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Create a plan so members can take out a subscription.') }}</flux:callout.text>
        </flux:callout>
    @else
        <x-table-card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Price') }}</flux:table.column>
                <flux:table.column>{{ __('Interval') }}</flux:table.column>
                <flux:table.column>{{ __('Credits') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->plans as $plan)
                    <flux:table.row :key="$plan->id">
                        <flux:table.cell class="sp-strong">
                            {{ $plan->name }}
                            @if ($plan->description)
                                <flux:text class="text-xs">{{ $plan->description }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $plan->formattedPrice() }}</flux:table.cell>
                        <flux:table.cell>{{ $plan->interval->label() }}</flux:table.cell>
                        <flux:table.cell>{{ $plan->isUnlimited() ? __('Unlimited') : $plan->credits }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$plan->active ? 'green' : 'zinc'">
                                {{ $plan->active ? __('Active') : __('Archived') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />

                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="editPlan({{ $plan->id }})">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item icon="archive-box" wire:click="toggleActive({{ $plan->id }})">
                                        {{ $plan->active ? __('Archive') : __('Activate') }}
                                    </flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        </x-table-card>
    @endif

    <flux:modal name="plan" class="md:w-96">
        <form wire:submit="savePlan" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingPlanId ? __('Edit subscription plan') : __('New subscription plan') }}</flux:heading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" placeholder="Onbeperkt" required autofocus />
            <flux:textarea wire:model="description" :label="__('Description')" rows="2" />
            <flux:input wire:model="price" :label="__('Price (€)')" type="number" step="0.01" min="0" required />
            <flux:select wire:model="interval" :label="__('Billing interval')">
                @foreach (\App\Enums\BillingInterval::cases() as $intervalOption)
                    <flux:select.option value="{{ $intervalOption->value }}">{{ $intervalOption->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:field>
                <flux:input wire:model="credits" :label="__('Lesson credits per period')" type="number" min="1" />
                <flux:description>{{ __('Leave empty for unlimited. Every lesson reservation (one-time or recurring) uses 1 credit.') }}</flux:description>
            </flux:field>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ $editingPlanId ? __('Save changes') : __('Create plan') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
