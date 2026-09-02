<div class="flex w-full flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Strip cards') }}</flux:heading>
            <flux:text class="mt-1">{{ $club->name }}</flux:text>
        </div>

        <flux:modal.trigger name="create-plan">
            <flux:button variant="primary" icon="plus">{{ __('New plan') }}</flux:button>
        </flux:modal.trigger>
    </div>

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
                        <flux:table.cell variant="strong">{{ $plan->name }}</flux:table.cell>
                        <flux:table.cell>{{ $plan->strips }}</flux:table.cell>
                        <flux:table.cell>{{ $plan->formattedPrice() }}</flux:table.cell>
                        <flux:table.cell>{{ $plan->validity_months ? trans_choice(':count month|:count months', $plan->validity_months) : __('Unlimited') }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$plan->active ? 'green' : 'zinc'">
                                {{ $plan->active ? __('Active') : __('Archived') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button variant="ghost" size="sm" wire:click="toggleActive({{ $plan->id }})">
                                {{ $plan->active ? __('Archive') : __('Activate') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        </x-table-card>
    @endif

    <flux:modal name="create-plan" class="md:w-96">
        <form wire:submit="createPlan" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New strip card plan') }}</flux:heading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" placeholder="10-strippenkaart" required autofocus />
            <flux:input wire:model="strips" :label="__('Number of strips')" type="number" min="1" required />
            <flux:input wire:model="price" :label="__('Price (€)')" type="number" step="0.01" min="0" required />
            <flux:input wire:model="validity_months" :label="__('Valid for (months)')" type="number" min="1" />

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Create plan') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
