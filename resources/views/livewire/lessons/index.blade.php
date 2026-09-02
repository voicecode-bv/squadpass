<div class="flex w-full flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Lessons') }}</flux:heading>
            <flux:text class="mt-1">{{ $club->name }}</flux:text>
        </div>

        <flux:button variant="primary" icon="plus" :href="route('clubs.lessons.create', $club)" wire:navigate>
            {{ __('New lesson') }}
        </flux:button>
    </div>

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
                        <flux:table.cell variant="strong">
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
                            @forelse ($lesson->trainers as $trainer)
                                <x-user-chip :user="$trainer->user" class="me-2" wire:key="lesson-trainer-{{ $lesson->id }}-{{ $trainer->id }}" />
                            @empty
                                -
                            @endforelse
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$lesson->active ? 'green' : 'zinc'">
                                {{ $lesson->active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button variant="ghost" size="sm" :href="route('clubs.lessons.edit', [$club, $lesson])" wire:navigate>
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
