<div class="flex w-full flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('My lessons') }}</flux:heading>
        <flux:text class="mt-1">{{ __('The lessons you teach in the next four weeks.') }}</flux:text>
    </div>

    @if ($this->occurrences->isEmpty())
        <flux:callout icon="academic-cap">
            <flux:callout.heading>{{ __('No upcoming lessons') }}</flux:callout.heading>
            <flux:callout.text>{{ __('You are not assigned to any upcoming lessons yet.') }}</flux:callout.text>
        </flux:callout>
    @else
        <x-table-card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Time') }}</flux:table.column>
                <flux:table.column>{{ __('Club') }}</flux:table.column>
                <flux:table.column>{{ __('Lesson') }}</flux:table.column>
                <flux:table.column>{{ __('Room') }}</flux:table.column>
                <flux:table.column>{{ __('Booked') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->occurrences as $occurrence)
                    @php $timezone = $occurrence->lesson->club->timezone; @endphp
                    <flux:table.row :key="$occurrence->id">
                        <flux:table.cell variant="strong">{{ $occurrence->starts_at->timezone($timezone)->isoFormat('dd D MMM') }}</flux:table.cell>
                        <flux:table.cell>{{ $occurrence->starts_at->timezone($timezone)->format('H:i') }}–{{ $occurrence->ends_at->timezone($timezone)->format('H:i') }}</flux:table.cell>
                        <flux:table.cell>{{ $occurrence->lesson->club->name }}</flux:table.cell>
                        <flux:table.cell>{{ $occurrence->lesson->name }}</flux:table.cell>
                        <flux:table.cell>{{ $occurrence->lesson->room?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $occurrence->booked_count }} / {{ $occurrence->effectiveCapacity() }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:modal.trigger name="participants">
                                <flux:button variant="ghost" size="sm" wire:click="selectOccurrence({{ $occurrence->id }})">
                                    {{ __('Participants') }}
                                </flux:button>
                            </flux:modal.trigger>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        </x-table-card>
    @endif

    <flux:modal name="participants" class="md:w-96">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Participants') }}</flux:heading>

            @if ($this->participants->isEmpty())
                <flux:text>{{ __('No participants yet') }}</flux:text>
            @else
                <ul class="space-y-2">
                    @foreach ($this->participants as $enrollment)
                        <li class="flex items-center justify-between" wire:key="participant-{{ $enrollment->id }}">
                            <flux:text><x-user-chip :user="$enrollment->user" /></flux:text>
                            <flux:badge size="sm" :color="$enrollment->isFixed() ? 'blue' : 'zinc'">
                                {{ $enrollment->type->label() }}
                            </flux:badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </flux:modal>
</div>
