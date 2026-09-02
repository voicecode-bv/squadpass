<div class="flex w-full flex-col gap-6">
    {{-- Page header --}}
    <div>
        <flux:heading size="xl">{{ __('Dashboard') }}</flux:heading>
        <flux:text class="mt-1">{{ $club->name }}</flux:text>
    </div>

    @if ($this->failedChargeCount > 0)
        <flux:callout variant="warning" icon="exclamation-triangle">
            <flux:callout.heading>{{ __('Failed charges') }}</flux:callout.heading>
            <flux:callout.text>
                {{ trans_choice(':count subscription charge could not be collected in the last 30 days.|:count subscription charges could not be collected in the last 30 days.', $this->failedChargeCount) }}
                <flux:link :href="route('clubs.subscriptions', $club)" wire:navigate>{{ __('View subscriptions') }}</flux:link>
            </flux:callout.text>
        </flux:callout>
    @endif

    {{-- KPI cards --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <span class="flex size-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                <flux:icon.users class="size-5" />
            </span>
            <p class="mt-4 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Active members') }}</p>
            <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $this->activeMemberCount }}</p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <span class="flex size-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                <flux:icon.user-plus class="size-5" />
            </span>
            <p class="mt-4 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('New members last month') }}</p>
            <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $this->newMembersLastMonth }}</p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <span class="flex size-10 items-center justify-center rounded-lg bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400">
                <flux:icon.banknotes class="size-5" />
            </span>
            <p class="mt-4 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Revenue last month') }}</p>
            <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">&euro; {{ number_format($this->revenueLastMonthCents / 100, 2, ',', '.') }}</p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <span class="flex size-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                <flux:icon.finger-print class="size-5" />
            </span>
            <p class="mt-4 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Check-ins this month') }}</p>
            <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">{{ $this->checkInsThisMonth }}</p>
        </div>
    </div>

    {{-- Charts --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('Active members per month') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('Last 12 months') }}</flux:text>
            <x-charts.area class="mt-4" :values="$this->activeMembersPerMonth['values']" :labels="$this->activeMembersPerMonth['labels']" svg-class="h-40" />
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm dark:bg-zinc-900">
            <flux:heading size="lg">{{ __('New members per month') }}</flux:heading>
            <flux:text class="mt-1 text-sm">{{ __('Last 12 months') }}</flux:text>
            <x-charts.area class="mt-4" :values="$this->newMembersPerMonth['values']" :labels="$this->newMembersPerMonth['labels']" svg-class="h-40" />
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
</div>
