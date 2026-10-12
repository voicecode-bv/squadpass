<div class="flex w-full flex-col gap-6">
    @php
        $occurrences = $this->occurrences;
        $showClub = $occurrences->pluck('club_id')->unique()->count() > 1;

        /** A stable dot colour per lesson, so a member recognises the same lesson at a glance. */
        $lessonColors = ['bg-blue-500', 'bg-emerald-500', 'bg-violet-500', 'bg-amber-500', 'bg-rose-500', 'bg-teal-500'];
    @endphp

    <x-portal.hero
        :title="__('Lesson schedule')"
        :subtitle="__('Upcoming lessons at your clubs for the next four weeks.')"
    />

    @if ($occurrences->isEmpty())
        <x-portal.empty
            icon="calendar"
            :heading="__('No upcoming lessons')"
            :text="__('There are no lessons scheduled at your clubs, or you are not an active member of a club yet.')"
        />
    @else
        <div class="sp-panel animate-rise overflow-hidden">
            @foreach ($occurrences->groupBy(fn ($occurrence) => $occurrence->starts_at->timezone($occurrence->club->timezone)->toDateString()) as $day => $occurrencesOfDay)
                @php
                    $dayDate = $occurrencesOfDay->first()->starts_at->timezone($occurrencesOfDay->first()->club->timezone);
                @endphp

                <section class="flex gap-4 border-zinc-100 px-4 py-5 not-first:border-t sm:gap-6 sm:px-6 dark:border-zinc-800" wire:key="day-{{ $day }}">
                    <div class="w-9 shrink-0 text-center">
                        <div class="text-[11px] font-semibold tracking-wide text-zinc-400 uppercase dark:text-zinc-500">
                            {{ ucfirst($dayDate->isoFormat('dd')) }}
                        </div>
                        <div @class([
                            'text-2xl leading-tight font-semibold tabular-nums',
                            'text-blue-600 dark:text-blue-400' => $dayDate->isToday(),
                            'text-zinc-900 dark:text-white' => ! $dayDate->isToday(),
                        ])>
                            {{ $dayDate->format('j') }}
                        </div>
                    </div>

                    <div class="flex min-w-0 flex-1 flex-col gap-0.5 border-l border-zinc-100 pl-4 sm:pl-6 dark:border-zinc-800">
                        @foreach ($occurrencesOfDay as $occurrence)
                            @php
                                $timezone = $occurrence->club->timezone;
                                $isFixedEnrolled = isset($this->fixedEnrolledLessonIds[$occurrence->lesson_id]);
                                $isAbsent = isset($this->absentOccurrenceIds[$occurrence->id]);
                                $isSingleEnrolled = isset($this->singleEnrolledOccurrenceIds[$occurrence->id]);
                                $isAttending = $isSingleEnrolled || ($isFixedEnrolled && ! $isAbsent);
                                $isFull = $occurrence->booked_count >= $occurrence->effectiveCapacity();
                                $dotColor = $lessonColors[crc32((string) $occurrence->lesson_id) % count($lessonColors)];
                                $meta = collect([$showClub ? $occurrence->club->name : null, $occurrence->lesson->room?->name])->filter()->join(' · ');
                            @endphp

                            <button type="button" wire:click="showOccurrence({{ $occurrence->id }})" wire:key="occ-{{ $occurrence->id }}"
                                    class="group -mx-2 grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-3 rounded-xl px-2 py-2.5 text-left transition hover:bg-zinc-100 sm:grid-cols-[auto_minmax(0,2.4fr)_minmax(0,1fr)_minmax(0,1fr)_auto] sm:gap-x-4 dark:hover:bg-white/5">
                                <span class="size-2 shrink-0 rounded-full {{ $dotColor }} {{ $isAttending ? '' : 'opacity-70' }}" aria-hidden="true"></span>

                                <span class="min-w-0">
                                    <span class="sp-strong block truncate">{{ $occurrence->lesson->name }}</span>
                                    <span class="sp-muted block text-sm tabular-nums">
                                        {{ $occurrence->starts_at->timezone($timezone)->format('H:i') }} - {{ $occurrence->ends_at->timezone($timezone)->format('H:i') }}
                                    </span>
                                </span>

                                <span class="sp-muted hidden truncate text-sm sm:block">{{ $meta }}</span>

                                <span class="sp-muted hidden truncate text-sm sm:block">
                                    {{ $occurrence->lesson->trainers->map(fn ($trainer) => $trainer->user->name)->join(', ') }}
                                </span>

                                <span @class([
                                    'w-24 shrink-0 rounded-lg px-2 py-1.5 text-center text-xs font-medium tabular-nums sm:w-32',
                                    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $isAttending,
                                    'bg-zinc-100 text-zinc-500 dark:bg-white/5 dark:text-zinc-400' => ! $isAttending && $isFull,
                                    'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300' => ! $isAttending && ! $isFull,
                                ])>
                                    @if ($isAttending)
                                        {{ $isFixedEnrolled && ! $isSingleEnrolled ? __('Recurring') : __('Enrolled') }}
                                    @elseif ($isFull)
                                        {{ __('Full') }}
                                    @else
                                        {{ $occurrence->booked_count }} / {{ $occurrence->effectiveCapacity() }}
                                    @endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
    @endif

    <flux:modal name="occurrence" class="md:w-[32rem]">
        @php
            $selected = $this->selectedOccurrence;
        @endphp

        @if ($selected)
            @php
                $timezone = $selected->club->timezone;
                $start = $selected->starts_at->timezone($timezone);
                $end = $selected->ends_at->timezone($timezone);
                $isFixedEnrolled = isset($this->fixedEnrolledLessonIds[$selected->lesson_id]);
                $isAbsent = isset($this->absentOccurrenceIds[$selected->id]);
                $isSingleEnrolled = isset($this->singleEnrolledOccurrenceIds[$selected->id]);
                $isAttending = $isSingleEnrolled || ($isFixedEnrolled && ! $isAbsent);
                $capacity = max(1, $selected->effectiveCapacity());
                $spotsLeft = max(0, $selected->effectiveCapacity() - $selected->booked_count);
                $filledPercentage = min(100, (int) round($selected->booked_count / $capacity * 100));
                $trainers = $selected->lesson->trainers->map(fn ($trainer) => $trainer->user->name)->join(', ');
            @endphp

            <div class="flex flex-col gap-6">
                <div>
                    <flux:heading size="lg">{{ $selected->lesson->name }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ ucfirst($start->isoFormat('dddd D MMMM')) }} · {{ $start->format('H:i') }} - {{ $end->format('H:i') }}
                    </flux:text>
                </div>

                @if ($selected->lesson->description)
                    <flux:text>{{ $selected->lesson->description }}</flux:text>
                @endif

                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="sp-muted text-xs tracking-wide uppercase">{{ __('Club') }}</dt>
                        <dd class="sp-strong mt-0.5">{{ $selected->club->name }}</dd>
                    </div>
                    @if ($selected->lesson->room)
                        <div>
                            <dt class="sp-muted text-xs tracking-wide uppercase">{{ __('Room') }}</dt>
                            <dd class="sp-strong mt-0.5">{{ $selected->lesson->room->name }}</dd>
                        </div>
                    @endif
                    @if ($trainers !== '')
                        <div>
                            <dt class="sp-muted text-xs tracking-wide uppercase">{{ $selected->lesson->trainers->count() > 1 ? __('Trainers') : __('Trainer') }}</dt>
                            <dd class="sp-strong mt-0.5">{{ $trainers }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="flex items-center gap-3">
                    <div class="h-1.5 w-32 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                        <div class="sp-bar h-full rounded-full {{ $spotsLeft > 0 ? 'bg-blue-600' : 'bg-red-500' }}"
                             style="--sp-width: {{ $filledPercentage }}%"></div>
                    </div>
                    <span class="sp-muted text-xs tabular-nums">
                        {{ $spotsLeft > 0 ? trans_choice(':count spot left|:count spots left', $spotsLeft) : __('Full') }}
                    </span>
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    @if ($isAttending)
                        @if ($isSingleEnrolled)
                            <flux:button size="sm" variant="danger" wire:click="cancelSingle({{ $selected->id }})">{{ __('Cancel') }}</flux:button>
                        @else
                            <flux:button size="sm" variant="ghost" wire:click="markAbsent({{ $selected->id }})">{{ __('Absent this time') }}</flux:button>
                            <flux:button size="sm" variant="danger" wire:click="cancelFixed({{ $selected->id }})">{{ __('Cancel recurring enrollment') }}</flux:button>
                        @endif
                    @elseif ($spotsLeft > 0)
                        @if (! $isFixedEnrolled)
                            <flux:button size="sm" variant="ghost" wire:click="enrollFixed({{ $selected->id }})">{{ __('Enroll recurring') }}</flux:button>
                        @endif
                        <flux:button size="sm" variant="primary" wire:click="enrollSingle({{ $selected->id }})">{{ __('Enroll') }}</flux:button>
                    @else
                        <flux:badge color="red" size="sm">{{ __('Full') }}</flux:badge>
                    @endif
                </div>
            </div>
        @endif
    </flux:modal>
</div>
