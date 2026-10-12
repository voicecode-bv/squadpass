<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Schedule')" :subtitle="$club->name.' — '.__('upcoming lessons')" />

    <x-table-card>
    <flux:table :paginate="$this->occurrences">
        <flux:table.columns>
            <flux:table.column>{{ __('Date') }}</flux:table.column>
            <flux:table.column>{{ __('Time') }}</flux:table.column>
            <flux:table.column>{{ __('Lesson') }}</flux:table.column>
            <flux:table.column>{{ __('Booked') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->occurrences as $occurrence)
                <flux:table.row :key="$occurrence->id">
                    <flux:table.cell class="sp-strong">
                        {{ $occurrence->starts_at->timezone($club->timezone)->isoFormat('dd D MMM') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $occurrence->starts_at->timezone($club->timezone)->format('H:i') }}–{{ $occurrence->ends_at->timezone($club->timezone)->format('H:i') }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:link :href="route('clubs.occurrences.show', [$club, $occurrence])" wire:navigate>
                            {{ $occurrence->lesson->name }}
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $occurrence->booked_count }} / {{ $occurrence->effectiveCapacity() }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$occurrence->isCancelled() ? 'red' : 'green'">
                            {{ $occurrence->status->label() }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($occurrence->isCancelled())
                            <flux:button variant="ghost" size="sm" wire:click="restoreOccurrence({{ $occurrence->id }})">
                                {{ __('Restore') }}
                            </flux:button>
                        @else
                            <flux:button variant="ghost" size="sm" wire:click="cancelOccurrence({{ $occurrence->id }})">
                                {{ __('Cancel') }}
                            </flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
    </x-table-card>
</div>
