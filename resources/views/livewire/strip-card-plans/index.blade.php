<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Strip cards')" :subtitle="$club->name">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="newPlan">{{ __('New plan') }}</flux:button>
        </x-slot:actions>
    </x-portal.hero>

    @if ($this->plans->isEmpty())
        <flux:callout icon="ticket">
            <flux:callout.heading>{{ __('No strip card plans yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Create a plan so members can buy strip cards.') }}</flux:callout.text>
        </flux:callout>
    @else
        <x-table-card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Strips') }}</flux:table.column>
                <flux:table.column>{{ __('Price') }}</flux:table.column>
                <flux:table.column>{{ __('Validity') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->plans as $plan)
                    <flux:table.row :key="$plan->id">
                        <flux:table.cell class="sp-strong">{{ $plan->name }}</flux:table.cell>
                        <flux:table.cell>{{ $plan->strips }}</flux:table.cell>
                        <flux:table.cell>{{ $plan->formattedPrice() }}</flux:table.cell>
                        <flux:table.cell>{{ $plan->validity_months ? trans_choice(':count month|:count months', $plan->validity_months) : __('Unlimited') }}</flux:table.cell>
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
                <flux:heading size="lg">{{ $editingPlanId ? __('Edit strip card plan') : __('New strip card plan') }}</flux:heading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" placeholder="10-strippenkaart" required autofocus />
            <flux:input wire:model="strips" :label="__('Number of strips')" type="number" min="1" required />
            <flux:input wire:model="price" :label="__('Price (€)')" type="number" step="0.01" min="0" required />
            <flux:input wire:model="validity_months" :label="__('Valid for (months)')" type="number" min="1" />

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ $editingPlanId ? __('Save changes') : __('Create plan') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
