<div class="flex w-full flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Rooms') }}</flux:heading>
            <flux:text class="mt-1">{{ $club->name }}</flux:text>
        </div>

        <flux:modal.trigger name="create-room">
            <flux:button variant="primary" icon="plus">{{ __('New room') }}</flux:button>
        </flux:modal.trigger>
    </div>

    @if ($this->rooms->isEmpty())
        <flux:callout icon="map-pin">
            <flux:callout.heading>{{ __('No rooms yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Add rooms (courts, halls, studios) so you can schedule lessons in them.') }}</flux:callout.text>
        </flux:callout>
    @else
        <x-table-card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column>{{ __('Lessons') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->rooms as $room)
                    <flux:table.row :key="$room->id">
                        <flux:table.cell variant="strong">{{ $room->name }}</flux:table.cell>
                        <flux:table.cell class="max-w-64 truncate">{{ $room->description }}</flux:table.cell>
                        <flux:table.cell>{{ $room->lessons_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$room->active ? 'green' : 'zinc'">
                                {{ $room->active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button variant="ghost" size="sm" wire:click="toggleActive({{ $room->id }})">
                                {{ $room->active ? __('Deactivate') : __('Activate') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        </x-table-card>
    @endif

    <flux:modal name="create-room" class="md:w-96">
        <form wire:submit="createRoom" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New room') }}</flux:heading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" placeholder="Baan 1" required autofocus />
            <flux:input wire:model="description" :label="__('Description')" />

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Create room') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
