<div class="flex w-full flex-col gap-6">
    {{-- Page header --}}
    <div>
        <flux:heading size="xl">{{ $this->greeting }}, {{ auth()->user()->name }}</flux:heading>
        <flux:text class="mt-1">{{ now()->isoFormat('dddd D MMMM YYYY') }}</flux:text>
    </div>

    {{-- KPI cards --}}
    @if ($this->isMember)
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
                <span class="flex size-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                    <flux:icon.ticket class="size-5" />
                </span>
                <p class="mt-4 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Strips remaining') }}</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $this->stripsRemaining }}</p>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
                <span class="flex size-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                    <flux:icon.credit-card class="size-5" />
                </span>
                <p class="mt-4 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Active subscriptions') }}</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $this->activeSubscriptionCount }}</p>
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
                <span class="flex size-10 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400">
                    <flux:icon.finger-print class="size-5" />
                </span>
                <p class="mt-4 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Visits this month') }}</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $this->visitsThisMonth }}</p>
            </div>
        </div>
    @endif

    {{-- Failed charges warning --}}
    @if ($this->failedChargesByClub->isNotEmpty())
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('Failed charges') }}</flux:callout.heading>
            <flux:callout.text>
                {{ trans_choice(':count subscription charge could not be collected in the last 30 days.|:count subscription charges could not be collected in the last 30 days.', $this->failedChargesByClub->sum()) }}
                @foreach ($this->staffClubs as $club)
                    @if ($this->failedChargesByClub->has($club->id))
                        <flux:link :href="route('clubs.subscriptions', $club)" wire:navigate class="me-2">
                            {{ $club->name }} ({{ $this->failedChargesByClub[$club->id] }})
                        </flux:link>
                    @endif
                @endforeach
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- Club statistics --}}
    @if ($this->staffClubs->isNotEmpty())
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="flex flex-col rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
                <span class="flex size-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                    <flux:icon.user-plus class="size-5" />
                </span>
                <p class="mt-4 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('New members last month') }}</p>
                <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $this->newMembersLastMonth }}</p>
                <x-charts.area class="mt-auto pt-4" :values="$this->newMembersPerMonth['values']" svg-class="h-10" />
            </div>

            <div class="rounded-xl bg-white p-5 shadow-sm lg:col-span-2 dark:bg-zinc-900">
                <flux:heading size="lg">{{ __('Active members per month') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('Last 12 months') }}</flux:text>
                <x-charts.area class="mt-4" :values="$this->activeMembersPerMonth['values']" :labels="$this->activeMembersPerMonth['labels']" svg-class="h-40" />
            </div>
        </div>

        <section class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Revenue per month') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('Last 12 months') }}</flux:text>
            @php($revenueTitles = array_map(
                fn ($cents, $label) => $label.': € '.number_format($cents / 100, 2, ',', '.'),
                $this->revenuePerMonth['values'],
                $this->revenuePerMonth['labels'],
            ))
            @php($revenueDisplay = array_map(fn ($cents) => '€ '.number_format($cents / 100, 0, ',', '.'), $this->revenuePerMonth['values']))
            <x-charts.bars class="mt-4" :values="$this->revenuePerMonth['values']" :labels="$this->revenuePerMonth['labels']" :titles="$revenueTitles" :display="$revenueDisplay" />
        </section>
    @endif

    {{-- Clubs you manage --}}
    @if ($this->staffClubs->isNotEmpty())
        <section class="rounded-xl bg-white shadow-sm dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <flux:heading size="lg">{{ __('Your clubs') }}</flux:heading>
                <flux:button :href="route('clubs.index')" wire:navigate variant="ghost" size="sm" icon-trailing="arrow-right">
                    {{ __('My clubs') }}
                </flux:button>
            </div>

            <div class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->staffClubs as $club)
                    <a href="{{ $club->isOnboarded() ? route('clubs.dashboard', $club) : route('clubs.onboarding', $club) }}" wire:navigate
                       class="group rounded-lg bg-zinc-50 p-4 transition hover:bg-blue-50 dark:bg-zinc-800/50 dark:hover:bg-blue-500/10">
                        <div class="flex items-start justify-between">
                            <span class="flex size-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                                <flux:icon.building-storefront class="size-5" />
                            </span>
                            @unless ($club->isOnboarded())
                                <flux:badge size="sm" color="yellow">{{ __('Finish setup') }}</flux:badge>
                            @endunless
                        </div>
                        <flux:heading class="mt-3">{{ $club->name }}</flux:heading>
                        <flux:text class="mt-1 text-sm">
                            {{ trans_choice(':count active member|:count active members', $club->active_members_count) }}
                        </flux:text>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Lessons you teach --}}
    @if ($this->trainerOccurrences->isNotEmpty())
        <section class="rounded-xl bg-white shadow-sm dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <flux:heading size="lg">{{ __('Lessons you teach') }}</flux:heading>
                <flux:button :href="route('trainer.lessons')" wire:navigate variant="ghost" size="sm" icon-trailing="arrow-right">
                    {{ __('My lessons') }}
                </flux:button>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($this->trainerOccurrences as $occurrence)
                    @php($timezone = $occurrence->lesson->club->timezone)
                    <div class="flex items-center gap-4 px-5 py-4">
                        <div class="flex size-12 shrink-0 flex-col items-center justify-center rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">
                            <span class="text-[10px] font-semibold uppercase leading-none">{{ $occurrence->starts_at->timezone($timezone)->isoFormat('MMM') }}</span>
                            <span class="mt-0.5 text-lg font-bold leading-none">{{ $occurrence->starts_at->timezone($timezone)->isoFormat('D') }}</span>
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
        </section>
    @endif

    {{-- Your upcoming lessons --}}
    @if ($this->isMember)
        <section class="rounded-xl bg-white shadow-sm dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-100 px-5 py-4 dark:border-zinc-800">
                <flux:heading size="lg">{{ __('Your upcoming lessons') }}</flux:heading>
                <flux:button :href="route('portal.schedule')" wire:navigate variant="ghost" size="sm" icon-trailing="arrow-right">
                    {{ __('Lesson schedule') }}
                </flux:button>
            </div>

            @if ($this->upcomingEnrollments->isEmpty())
                <div class="flex flex-col items-center gap-3 px-5 py-10 text-center">
                    <span class="flex size-11 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400">
                        <flux:icon.calendar-days class="size-5" />
                    </span>
                    <div>
                        <flux:heading>{{ __('Nothing booked yet') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Browse the lesson schedule and book your next lesson.') }}</flux:text>
                    </div>
                    <flux:button :href="route('portal.schedule')" wire:navigate variant="primary" size="sm">
                        {{ __('Lesson schedule') }}
                    </flux:button>
                </div>
            @else
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->upcomingEnrollments as $enrollment)
                        @php($timezone = $enrollment->occurrence->club->timezone)
                        <div class="flex items-center gap-4 px-5 py-4">
                            <div class="flex size-12 shrink-0 flex-col items-center justify-center rounded-lg bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300">
                                <span class="text-[10px] font-semibold uppercase leading-none">{{ $enrollment->occurrence->starts_at->timezone($timezone)->isoFormat('MMM') }}</span>
                                <span class="mt-0.5 text-lg font-bold leading-none">{{ $enrollment->occurrence->starts_at->timezone($timezone)->isoFormat('D') }}</span>
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
        </section>
    @endif

    {{-- Empty state for brand-new accounts --}}
    @if ($this->staffClubs->isEmpty() && ! $this->isMember && $this->trainerOccurrences->isEmpty())
        <div class="flex flex-col items-center gap-4 rounded-xl bg-white p-12 text-center shadow-sm dark:bg-zinc-900">
            <span class="flex size-14 items-center justify-center rounded-2xl bg-linear-to-br from-blue-500 to-indigo-600 text-white shadow-lg shadow-blue-500/30">
                <flux:icon.rocket-launch class="size-7" />
            </span>
            <div>
                <flux:heading size="lg">{{ __('Welcome to SquadPass!') }}</flux:heading>
                <flux:text class="mt-2 max-w-md">{{ __('Create your own club, or ask your club for an invitation to get started.') }}</flux:text>
            </div>
            @can('create', \App\Models\Club::class)
                <flux:button :href="route('clubs.index')" wire:navigate variant="primary" icon="plus">
                    {{ __('Create your club') }}
                </flux:button>
            @endcan
        </div>
    @endif
</div>
