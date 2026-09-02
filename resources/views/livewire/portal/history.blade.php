<div class="flex w-full flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('My visits') }}</flux:heading>
    </div>

    @if ($this->checkIns->isEmpty())
        <flux:callout icon="finger-print">
            <flux:callout.heading>{{ __('No visits yet') }}</flux:callout.heading>
        </flux:callout>
    @else
        <x-table-card>
        <flux:table :paginate="$this->checkIns">
            <flux:table.columns>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column>{{ __('Club') }}</flux:table.column>
                <flux:table.column>{{ __('Lesson') }}</flux:table.column>
                <flux:table.column>{{ __('Coverage') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->checkIns as $checkIn)
                    <flux:table.row :key="$checkIn->id">
                        <flux:table.cell variant="strong">
                            {{ $checkIn->checked_in_at->timezone($checkIn->club->timezone)->isoFormat('D MMM YYYY HH:mm') }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $checkIn->club->name }}</flux:table.cell>
                        <flux:table.cell>{{ $checkIn->occurrence?->lesson->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm">{{ $checkIn->coverageLabel() }}</flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        </x-table-card>
    @endif
</div>
