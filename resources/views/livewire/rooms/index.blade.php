<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Rooms')" :subtitle="$club->name">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="newRoom">{{ __('New room') }}</flux:button>
        </x-slot:actions>
    </x-portal.hero>

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
                        <flux:table.cell class="sp-strong">{{ $room->name }}</flux:table.cell>
                        <flux:table.cell class="max-w-64 truncate">{{ $room->description }}</flux:table.cell>
                        <flux:table.cell>{{ $room->lessons_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$room->active ? 'green' : 'zinc'">
                                {{ $room->active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown position="bottom" align="end">
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom" :aria-label="__('Actions')" />

                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="editRoom({{ $room->id }})">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item icon="power" wire:click="toggleActive({{ $room->id }})">
                                        {{ $room->active ? __('Deactivate') : __('Activate') }}
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

    <flux:modal name="room" class="md:w-96">
        <form wire:submit="saveRoom" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingRoomId ? __('Edit room') : __('New room') }}</flux:heading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" placeholder="Baan 1" required autofocus />
            <flux:input wire:model="description" :label="__('Description')" />

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ $editingRoomId ? __('Save changes') : __('Create room') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
