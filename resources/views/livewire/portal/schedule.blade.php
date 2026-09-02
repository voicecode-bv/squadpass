<div class="flex w-full flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Lesson schedule') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Upcoming lessons at your clubs for the next four weeks.') }}</flux:text>
    </div>

    @if ($this->occurrences->isEmpty())
        <flux:callout icon="calendar">
            <flux:callout.heading>{{ __('No upcoming lessons') }}</flux:callout.heading>
            <flux:callout.text>{{ __('There are no lessons scheduled at your clubs, or you are not an active member of a club yet.') }}</flux:callout.text>
        </flux:callout>
    @else
        <div class="flex flex-col gap-3">
            @foreach ($this->occurrences as $occurrence)
                @php
                    $timezone = $occurrence->club->timezone;
                    $isFixedEnrolled = isset($this->fixedEnrolledLessonIds[$occurrence->lesson_id]);
                    $isAbsent = isset($this->absentOccurrenceIds[$occurrence->id]);
                    $isSingleEnrolled = isset($this->singleEnrolledOccurrenceIds[$occurrence->id]);
                    $isAttending = $isSingleEnrolled || ($isFixedEnrolled && ! $isAbsent);
                    $spotsLeft = max(0, $occurrence->effectiveCapacity() - $occurrence->booked_count);
                @endphp

                <div class="flex flex-col gap-3 rounded-2xl bg-white p-4 shadow-sm transition hover:shadow-md sm:flex-row sm:items-center sm:justify-between dark:bg-zinc-900" wire:key="occ-{{ $occurrence->id }}">
                    <div class="flex items-center gap-4">
                        <div class="flex w-14 flex-col items-center rounded-xl bg-blue-50 py-2 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">
                            <span class="text-xs font-semibold uppercase">{{ $occurrence->starts_at->timezone($timezone)->isoFormat('dd') }}</span>
                            <span class="text-lg leading-tight font-semibold">{{ $occurrence->starts_at->timezone($timezone)->format('j') }}</span>
                            <span class="text-xs uppercase">{{ $occurrence->starts_at->timezone($timezone)->isoFormat('MMM') }}</span>
                        </div>
                        <div>
                            <flux:heading>{{ $occurrence->lesson->name }}</flux:heading>
                            <flux:text class="text-sm">
                                {{ $occurrence->club->name }} ·
                                {{ ucfirst($occurrence->starts_at->timezone($timezone)->isoFormat('dddd')) }}
                                {{ $occurrence->starts_at->timezone($timezone)->format('H:i') }}–{{ $occurrence->ends_at->timezone($timezone)->format('H:i') }}
                                @if ($occurrence->lesson->room)
                                    · {{ $occurrence->lesson->room->name }}
                                @endif
                                @if ($occurrence->lesson->trainers->isNotEmpty())
                                    · {{ $occurrence->lesson->trainers->map(fn ($trainer) => $trainer->user->name)->join(', ') }}
                                @endif
                            </flux:text>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($isAttending)
                            <flux:badge color="green" size="sm">{{ $isFixedEnrolled && ! $isSingleEnrolled ? __('Recurring') : __('Enrolled') }}</flux:badge>
                            @if ($isSingleEnrolled)
                                <flux:button size="sm" variant="ghost" wire:click="cancelSingle({{ $occurrence->id }})">{{ __('Cancel') }}</flux:button>
                            @else
                                <flux:button size="sm" variant="ghost" wire:click="markAbsent({{ $occurrence->id }})">{{ __('Absent this time') }}</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="cancelFixed({{ $occurrence->id }})">{{ __('Cancel recurring enrollment') }}</flux:button>
                            @endif
                        @else
                            <flux:badge size="sm" :color="$spotsLeft > 0 ? 'zinc' : 'red'">
                                {{ $spotsLeft > 0 ? trans_choice(':count spot left|:count spots left', $spotsLeft) : __('Full') }}
                            </flux:badge>
                            @if ($spotsLeft > 0)
                                <flux:button size="sm" wire:click="enrollSingle({{ $occurrence->id }})">{{ __('Enroll') }}</flux:button>
                                @if (! $isFixedEnrolled)
                                    <flux:button size="sm" variant="ghost" wire:click="enrollFixed({{ $occurrence->id }})">{{ __('Enroll recurring') }}</flux:button>
                                @endif
                            @endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
