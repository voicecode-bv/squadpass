<div class="flex w-full flex-col gap-6">
    <x-portal.hero
        :eyebrow="now()->isoFormat('dddd D MMMM YYYY')"
        :title="$this->greeting.', '.auth()->user()->name"
    />

    {{-- KPI band --}}
    @if ($this->isMember)
        <x-portal.kpi-band :items="[
            ['label' => __('Strips remaining'), 'value' => $this->stripsRemaining, 'countUp' => $this->stripsRemaining],
            ['label' => __('Active subscriptions'), 'value' => $this->activeSubscriptionCount, 'countUp' => $this->activeSubscriptionCount],
            ['label' => __('Visits this month'), 'value' => $this->visitsThisMonth, 'countUp' => $this->visitsThisMonth],
        ]" />
    @endif

    {{-- Club statistics --}}
    @if ($this->staffClubs->isNotEmpty())
        <div class="grid gap-4 lg:grid-cols-3">
            <x-portal.card :delay="80" class="flex flex-col p-5">
                <flux:heading class="sp-card-title">{{ __('New members') }}</flux:heading>
                <flux:text class="sp-card-subtitle mt-1">{{ __('Last month') }}</flux:text>
                <p class="mt-3 text-3xl font-bold tracking-tight text-zinc-900 tabular-nums dark:text-white"
                   x-data="spCountUp({{ (int) $this->newMembersLastMonth }})" x-text="displayed">{{ $this->newMembersLastMonth }}</p>
                <x-charts.area class="-mx-5 -mb-5 mt-auto pt-4" :values="$this->newMembersPerMonth['values']" svg-class="h-10" />
            </x-portal.card>

            <x-portal.card :delay="150" class="p-5 lg:col-span-2">
                <flux:heading class="sp-card-title">{{ __('Active members per month') }}</flux:heading>
                <flux:text class="sp-card-subtitle mt-1">{{ __('Last 12 months') }}</flux:text>
                <x-charts.area class="-mx-5 -mb-5 mt-4" label-class="px-5 pb-5" :values="$this->activeMembersPerMonth['values']" :labels="$this->activeMembersPerMonth['labels']" svg-class="h-40" />
            </x-portal.card>
        </div>

        @php($revenueTitles = array_map(
            fn ($cents, $label) => $label.': € '.number_format($cents / 100, 2, ',', '.'),
            $this->revenuePerMonth['values'],
            $this->revenuePerMonth['labels'],
        ))
        @php($revenueDisplay = array_map(fn ($cents) => '€ '.number_format($cents / 100, 0, ',', '.'), $this->revenuePerMonth['values']))

        <x-portal.card :delay="200" class="p-5">
            <flux:heading class="sp-card-title">{{ __('Revenue per month') }}</flux:heading>
            <flux:text class="sp-card-subtitle mt-1">{{ __('Last 12 months') }}</flux:text>
            <x-charts.bars class="mt-4" :values="$this->revenuePerMonth['values']" :labels="$this->revenuePerMonth['labels']" :titles="$revenueTitles" :display="$revenueDisplay" />
        </x-portal.card>
    @endif

    {{-- Clubs you manage --}}
    @if ($this->staffClubs->isNotEmpty())
        <x-portal.card :delay="250" class="hover:translate-y-0 hover:shadow-sm">
            <div class="relative flex items-center justify-between border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <flux:heading class="sp-card-title">{{ __('Your clubs') }}</flux:heading>
                <flux:button :href="route('clubs.index')" wire:navigate variant="ghost" size="sm" icon-trailing="arrow-right">
                    {{ __('My clubs') }}
                </flux:button>
            </div>

            <div class="relative grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->staffClubs as $clubIndex => $club)
                    <a href="{{ $club->isOnboarded() ? route('clubs.dashboard', $club) : route('clubs.onboarding', $club) }}" wire:navigate
                       class="stagger animate-rise group/club relative overflow-hidden rounded-xl bg-zinc-50 transition duration-300 hover:-translate-y-0.5 hover:bg-blue-50 dark:bg-zinc-800/50 dark:hover:bg-blue-500/10"
                       style="--d: {{ min(600, 300 + $clubIndex * 60) }}ms">
                        @if ($club->photoUrl())
                            <img src="{{ $club->photoUrl() }}" alt="{{ $club->name }}"
                                 class="h-56 w-full object-cover transition duration-300 group-hover/club:scale-[1.03]" loading="lazy" />
                        @else
                            <div class="flex h-56 w-full items-center justify-center bg-blue-50 text-2xl font-semibold text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                                {{ str($club->name)->substr(0, 1)->upper() }}
                            </div>
                        @endif

                        <div class="p-4">
                            @unless ($club->isOnboarded())
                                <flux:badge size="sm" color="yellow" class="mb-2">{{ __('Finish setup') }}</flux:badge>
                            @endunless

                            <flux:heading class="sp-card-title">{{ $club->name }}</flux:heading>
                            <flux:text class="sp-card-subtitle mt-1">
                                {{ trans_choice(':count active member|:count active members', $club->active_members_count) }}
                            </flux:text>
                        </div>
                    </a>
                @endforeach
            </div>
        </x-portal.card>
    @endif

    {{-- Lessons you teach --}}
    @if ($this->trainerOccurrences->isNotEmpty())
        <x-portal.card :delay="300" class="hover:translate-y-0 hover:shadow-sm">
            <div class="relative flex items-center justify-between border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <flux:heading class="sp-card-title">{{ __('Lessons you teach') }}</flux:heading>
                <flux:button :href="route('trainer.lessons')" wire:navigate variant="ghost" size="sm" icon-trailing="arrow-right">
                    {{ __('My lessons') }}
                </flux:button>
            </div>

            <div class="relative divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($this->trainerOccurrences as $occurrenceIndex => $occurrence)
                    @php($timezone = $occurrence->lesson->club->timezone)
                    <div class="stagger animate-rise group/row flex items-center gap-4 px-5 py-4 transition hover:bg-blue-50/60 dark:hover:bg-blue-500/5"
                         style="--d: {{ min(600, 320 + $occurrenceIndex * 50) }}ms">
                        <div class="flex size-12 shrink-0 flex-col items-center justify-center rounded-xl bg-blue-50 text-blue-700 transition duration-300 group-hover/row:bg-blue-600 group-hover/row:text-white dark:bg-blue-500/10 dark:text-blue-300">
                            <span class="text-[10px] leading-none font-semibold uppercase">{{ $occurrence->starts_at->timezone($timezone)->isoFormat('MMM') }}</span>
                            <span class="mt-0.5 text-lg leading-none font-bold">{{ $occurrence->starts_at->timezone($timezone)->isoFormat('D') }}</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <flux:heading class="truncate">{{ $occurrence->lesson->name }}</flux:heading>
                            <flux:text class="truncate text-sm">
                                {{ $occurrence->lesson->club->name }}
                                @if ($occurrence->lesson->room)
                                    &middot; {{ $occurrence->lesson->room->name }}
                                @endif
                            </flux:text>
                        </div>
                        <flux:badge size="sm" color="blue">
                            {{ $occurrence->starts_at->timezone($timezone)->format('H:i') }}–{{ $occurrence->ends_at->timezone($timezone)->format('H:i') }}
                        </flux:badge>
                    </div>
                @endforeach
            </div>
        </x-portal.card>
    @endif

    {{-- Your upcoming lessons --}}
    @if ($this->isMember)
        <x-portal.card :delay="350" class="hover:translate-y-0 hover:shadow-sm">
            <div class="relative flex items-center justify-between border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <flux:heading class="sp-card-title">{{ __('Your upcoming lessons') }}</flux:heading>
                <flux:button :href="route('portal.schedule')" wire:navigate variant="ghost" size="sm" icon-trailing="arrow-right">
                    {{ __('Lesson schedule') }}
                </flux:button>
            </div>

            @if ($this->upcomingEnrollments->isEmpty())
                <div class="relative flex flex-col items-center gap-3 px-5 py-10 text-center">
                    <span class="animate-float relative flex size-12 items-center justify-center rounded-2xl bg-blue-600 text-white">
                        <flux:icon.calendar-days class="size-5" />
                    </span>
                    <div>
                        <flux:heading class="sp-card-title">{{ __('Nothing booked yet') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Browse the lesson schedule and book your next lesson.') }}</flux:text>
                    </div>
                    <flux:button :href="route('portal.schedule')" wire:navigate variant="primary" size="sm"
                                >
                        {{ __('Lesson schedule') }}
                    </flux:button>
                </div>
            @else
                <div class="relative divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->upcomingEnrollments as $enrollmentIndex => $enrollment)
                        @php($timezone = $enrollment->occurrence->club->timezone)
                        <div class="stagger animate-rise group/row flex items-center gap-4 px-5 py-4 transition hover:bg-blue-50/60 dark:hover:bg-blue-500/5"
                             style="--d: {{ min(600, 380 + $enrollmentIndex * 50) }}ms">
                            <div class="flex size-12 shrink-0 flex-col items-center justify-center rounded-xl bg-blue-50 text-blue-700 transition duration-300 group-hover/row:bg-blue-600 group-hover/row:text-white dark:bg-blue-500/10 dark:text-blue-300">
                                <span class="text-[10px] leading-none font-semibold uppercase">{{ $enrollment->occurrence->starts_at->timezone($timezone)->isoFormat('MMM') }}</span>
                                <span class="mt-0.5 text-lg leading-none font-bold">{{ $enrollment->occurrence->starts_at->timezone($timezone)->isoFormat('D') }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <flux:heading class="truncate">{{ $enrollment->occurrence->lesson->name }}</flux:heading>
                                <flux:text class="truncate text-sm">{{ $enrollment->occurrence->club->name }}</flux:text>
                            </div>
                            <flux:badge size="sm" color="blue">
                                {{ $enrollment->occurrence->starts_at->timezone($timezone)->format('H:i') }}
                            </flux:badge>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-portal.card>
    @endif

    {{-- Empty state for brand-new accounts --}}
    @if ($this->staffClubs->isEmpty() && ! $this->isMember && $this->trainerOccurrences->isEmpty())
        <x-portal.empty icon="rocket-launch" :heading="__('Welcome to SquadPass!')" :text="__('Create your own club, or ask your club for an invitation to get started.')">
            @can('create', \App\Models\Club::class)
                <flux:button :href="route('clubs.index')" wire:navigate variant="primary" icon="plus"
                            >
                    {{ __('Create your club') }}
                </flux:button>
            @endcan
        </x-portal.empty>
    @endif
</div>
