<div class="flex w-full flex-col gap-8">
    <x-portal.hero
        :eyebrow="__('Your lessons')"
        :title="__('My enrollments')"
        :subtitle="__('Everything you are signed up for, recurring and one-time.')"
    >
        @if ($this->fixedEnrollments->isNotEmpty() || $this->singleEnrollments->isNotEmpty())
            <div class="grid gap-3 sm:grid-cols-2">
                <x-portal.stat :count-up="$this->fixedEnrollments->count()" :value="$this->fixedEnrollments->count()" :label="__('Recurring lessons')" :delay="260" />
                <x-portal.stat :count-up="$this->singleEnrollments->count()" :value="$this->singleEnrollments->count()" :label="__('One-time lessons')" :delay="320" />
            </div>
        @endif
    </x-portal.hero>

    @if ($this->fixedEnrollments->isEmpty() && $this->singleEnrollments->isEmpty())
        <x-portal.empty icon="clipboard-document-check" :heading="__('No enrollments yet')" :text="__('Sign up for a lesson and it shows up here right away.')">
            <flux:button variant="primary" icon="calendar-days" :href="route('portal.schedule')" wire:navigate
                        >
                {{ __('Lesson schedule') }}
            </flux:button>
        </x-portal.empty>
    @endif

    @if ($this->fixedEnrollments->isNotEmpty())
        <div class="flex flex-col gap-3">
            <div class="stagger animate-slide-in flex items-center gap-3" style="--d: 100ms">
                <h2 class="text-sm font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('Recurring lessons') }}</h2>
                <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800"></span>
            </div>

            @foreach ($this->fixedEnrollments as $enrollmentIndex => $enrollment)
                <x-portal.card :delay="min(600, $enrollmentIndex * 60)" wire:key="fixed-{{ $enrollment->id }}" class="p-4">

                    <div class="relative flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <flux:heading class="sp-card-title truncate transition group-hover:text-blue-600 dark:group-hover:text-blue-400">{{ $enrollment->lesson->name }}</flux:heading>
                            <flux:text class="truncate text-sm">
                                {{ $enrollment->lesson->club->name }} ·
                                {{ ucfirst(\Illuminate\Support\Carbon::now()->startOfWeek()->addDays($enrollment->lesson->day_of_week - 1)->dayName) }}
                                {{ substr($enrollment->lesson->start_time, 0, 5) }}
                            </flux:text>
                        </div>
                        <flux:button size="sm" variant="danger" wire:click="cancel({{ $enrollment->id }})" class="shrink-0 transition hover:scale-105">
                            {{ __('Cancel recurring enrollment') }}
                        </flux:button>
                    </div>
                </x-portal.card>
            @endforeach
        </div>
    @endif

    @if ($this->singleEnrollments->isNotEmpty())
        <div class="flex flex-col gap-3">
            <div class="stagger animate-slide-in flex items-center gap-3" style="--d: 140ms">
                <h2 class="text-sm font-semibold tracking-wide text-zinc-500 uppercase dark:text-zinc-400">{{ __('One-time lessons') }}</h2>
                <span class="h-px flex-1 bg-zinc-200 dark:bg-zinc-800"></span>
            </div>

            @foreach ($this->singleEnrollments as $enrollmentIndex => $enrollment)
                @php($timezone = $enrollment->occurrence->club->timezone)

                <x-portal.card :delay="min(600, $enrollmentIndex * 60)" wire:key="single-{{ $enrollment->id }}" class="p-4">

                    <div class="relative flex items-center justify-between gap-4">
                        <div class="flex min-w-0 items-center gap-4">
                            <div class="flex w-14 shrink-0 flex-col items-center rounded-xl bg-blue-50 py-2 text-blue-700 transition duration-300 group-hover:bg-blue-600 group-hover:text-white dark:bg-blue-500/10 dark:text-blue-300">
                                <span class="text-xs uppercase">{{ $enrollment->occurrence->starts_at->timezone($timezone)->isoFormat('MMM') }}</span>
                                <span class="text-lg leading-none font-bold">{{ $enrollment->occurrence->starts_at->timezone($timezone)->format('j') }}</span>
                            </div>
                            <div class="min-w-0">
                                <flux:heading class="sp-card-title truncate transition group-hover:text-blue-600 dark:group-hover:text-blue-400">{{ $enrollment->occurrence->lesson->name }}</flux:heading>
                                <flux:text class="truncate text-sm">
                                    {{ $enrollment->occurrence->club->name }} ·
                                    {{ $enrollment->occurrence->starts_at->timezone($timezone)->isoFormat('dd D MMM HH:mm') }}
                                </flux:text>
                            </div>
                        </div>
                        <flux:button size="sm" variant="danger" wire:click="cancel({{ $enrollment->id }})" class="shrink-0 transition hover:scale-105">
                            {{ __('Cancel') }}
                        </flux:button>
                    </div>
                </x-portal.card>
            @endforeach
        </div>
    @endif
</div>
