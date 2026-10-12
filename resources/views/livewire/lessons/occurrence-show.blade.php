<div class="flex w-full flex-col gap-6">
    @php
        $occurrenceStart = $occurrence->starts_at->timezone($club->timezone);
        $occurrenceEnd = $occurrence->ends_at->timezone($club->timezone);
        $occurrenceSummary = $occurrenceStart->isoFormat('dddd D MMMM YYYY').', '
            .$occurrenceStart->format('H:i').'–'.$occurrenceEnd->format('H:i')
            .' — '.$occurrence->booked_count.' / '.$occurrence->effectiveCapacity().' '.__('booked');
    @endphp

    <x-portal.hero :title="$occurrence->lesson->name" :subtitle="$occurrenceSummary" />

    @if ($this->participants->isEmpty())
        <flux:callout icon="users">
            <flux:callout.heading>{{ __('No participants yet') }}</flux:callout.heading>
        </flux:callout>
    @else
        <x-table-card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Enrollment') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->participants as $enrollment)
                    <flux:table.row :key="$enrollment->id">
                        <flux:table.cell class="sp-strong">{{ $enrollment->user->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$enrollment->isFixed() ? 'blue' : 'zinc'">
                                {{ $enrollment->type->label() }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="flex justify-end gap-2">
                            @if ($enrollment->isFixed())
                                <flux:button variant="ghost" size="sm" wire:click="markAbsent({{ $enrollment->id }})">
                                    {{ __('Absent this time') }}
                                </flux:button>
                            @endif
                            <flux:button variant="danger" size="sm" wire:click="removeEnrollment({{ $enrollment->id }})">
                                {{ $enrollment->isFixed() ? __('Cancel recurring enrollment') : __('Remove') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        </x-table-card>
    @endif
</div>
