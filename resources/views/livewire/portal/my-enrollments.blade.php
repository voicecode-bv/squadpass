<div class="flex w-full flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('My enrollments') }}</flux:heading>
    </div>

    <div class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('Recurring lessons') }}</flux:heading>

        @if ($this->fixedEnrollments->isEmpty())
            <flux:text>{{ __('You have no recurring enrollments.') }}</flux:text>
        @else
            @foreach ($this->fixedEnrollments as $enrollment)
                <div class="flex items-center justify-between gap-4 rounded-2xl bg-white p-4 shadow-sm dark:bg-zinc-900" wire:key="fixed-{{ $enrollment->id }}">
                    <div class="flex min-w-0 items-center gap-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                            <flux:icon.arrow-path class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <flux:heading class="truncate">{{ $enrollment->lesson->name }}</flux:heading>
                        <flux:text class="text-sm">
                            {{ $enrollment->lesson->club->name }} ·
                            {{ ucfirst(\Illuminate\Support\Carbon::now()->startOfWeek()->addDays($enrollment->lesson->day_of_week - 1)->dayName) }}
                            {{ substr($enrollment->lesson->start_time, 0, 5) }}
                        </flux:text>
                        </div>
                    </div>
                    <flux:button size="sm" variant="danger" wire:click="cancel({{ $enrollment->id }})">
                        {{ __('Cancel recurring enrollment') }}
                    </flux:button>
                </div>
            @endforeach
        @endif
    </div>

    <div class="flex flex-col gap-4">
        <flux:heading size="lg">{{ __('One-time lessons') }}</flux:heading>

        @if ($this->singleEnrollments->isEmpty())
            <flux:text>{{ __('You have no upcoming one-time enrollments.') }}</flux:text>
        @else
            @foreach ($this->singleEnrollments as $enrollment)
                @php($timezone = $enrollment->occurrence->club->timezone)
                <div class="flex items-center justify-between gap-4 rounded-2xl bg-white p-4 shadow-sm dark:bg-zinc-900" wire:key="single-{{ $enrollment->id }}">
                    <div class="flex min-w-0 items-center gap-4">
                        <div class="flex w-14 shrink-0 flex-col items-center rounded-xl bg-blue-50 py-2 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">
                            <span class="text-xs uppercase">{{ $enrollment->occurrence->starts_at->timezone($timezone)->isoFormat('MMM') }}</span>
                            <span class="text-lg font-semibold">{{ $enrollment->occurrence->starts_at->timezone($timezone)->format('j') }}</span>
                        </div>
                        <div class="min-w-0">
                            <flux:heading class="truncate">{{ $enrollment->occurrence->lesson->name }}</flux:heading>
                            <flux:text class="truncate text-sm">
                                {{ $enrollment->occurrence->club->name }} ·
                                {{ $enrollment->occurrence->starts_at->timezone($timezone)->isoFormat('dd D MMM HH:mm') }}
                            </flux:text>
                        </div>
                    </div>
                    <flux:button size="sm" variant="danger" wire:click="cancel({{ $enrollment->id }})">
                        {{ __('Cancel') }}
                    </flux:button>
                </div>
            @endforeach
        @endif
    </div>
</div>
