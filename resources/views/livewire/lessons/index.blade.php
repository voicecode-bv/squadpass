<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Lessons')" :subtitle="$club->name">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" :href="route('clubs.lessons.create', $club)" wire:navigate>
            {{ __('New lesson') }}
            </flux:button>
        </x-slot:actions>
    </x-portal.hero>

    @if ($this->lessons->isEmpty())
        <flux:callout icon="calendar">
            <flux:callout.heading>{{ __('No lessons yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Create a weekly lesson to build your schedule.') }}</flux:callout.text>
        </flux:callout>
    @else
        <x-table-card>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Lesson') }}</flux:table.column>
                <flux:table.column>{{ __('Day & time') }}</flux:table.column>
                <flux:table.column>{{ __('Capacity') }}</flux:table.column>
                <flux:table.column>{{ __('Trainers') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->lessons as $lesson)
                    <flux:table.row :key="$lesson->id">
                        <flux:table.cell class="sp-strong">
                            {{ $lesson->name }}
                            @if ($lesson->room)
                                <flux:text class="text-xs">{{ $lesson->room->name }}</flux:text>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ \Illuminate\Support\Carbon::now()->startOfWeek()->addDays($lesson->day_of_week - 1)->dayName }}
                            {{ substr($lesson->start_time, 0, 5) }} ({{ $lesson->duration_minutes }} {{ __('min') }})
                        </flux:table.cell>
                        <flux:table.cell>{{ $lesson->capacity }}</flux:table.cell>
                        <flux:table.cell>
                            {{ $lesson->trainers->map(fn ($trainer) => $trainer->user->name)->join(', ') ?: '-' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$lesson->active ? 'green' : 'zinc'">
                                {{ $lesson->active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button variant="ghost" size="sm" icon="pencil-square" :href="route('clubs.lessons.edit', [$club, $lesson])" wire:navigate>
                                {{ __('Edit') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
        </x-table-card>
    @endif
</div>
