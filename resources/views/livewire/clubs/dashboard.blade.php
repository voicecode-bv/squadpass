<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Dashboard')" :subtitle="$club->name" />

    {{-- KPI band --}}
    <x-portal.kpi-band :items="[
        ['label' => __('Active members').' · '.__('Right now'), 'value' => $this->activeMemberCount, 'countUp' => $this->activeMemberCount],
        ['label' => __('New members').' · '.__('Last month'), 'value' => $this->newMembersLastMonth, 'countUp' => $this->newMembersLastMonth],
        ['label' => __('Revenue').' · '.__('Last month'), 'value' => '€ '.number_format($this->revenueLastMonthCents / 100, 2, ',', '.')],
        ['label' => __('Check-ins').' · '.__('This month'), 'value' => $this->checkInsThisMonth, 'countUp' => $this->checkInsThisMonth],
    ]" />

    {{-- Charts --}}
    <div class="grid gap-4 lg:grid-cols-2">
        <x-portal.card :delay="280" class="p-5">
            <flux:heading class="sp-card-title">{{ __('Active members per month') }}</flux:heading>
            <flux:text class="sp-card-subtitle mt-1">{{ __('Last 12 months') }}</flux:text>
            <x-charts.area class="-mx-5 -mb-5 mt-4" label-class="px-5 pb-5" :values="$this->activeMembersPerMonth['values']" :labels="$this->activeMembersPerMonth['labels']" svg-class="h-40" />
        </x-portal.card>

        <x-portal.card :delay="330" class="p-5">
            <flux:heading class="sp-card-title">{{ __('New members per month') }}</flux:heading>
            <flux:text class="sp-card-subtitle mt-1">{{ __('Last 12 months') }}</flux:text>
            <x-charts.area class="-mx-5 -mb-5 mt-4" label-class="px-5 pb-5" :values="$this->newMembersPerMonth['values']" :labels="$this->newMembersPerMonth['labels']" svg-class="h-40" />
        </x-portal.card>
    </div>

    <x-portal.card :delay="380" class="p-5">
        <flux:heading class="sp-card-title">{{ __('Revenue per month') }}</flux:heading>
        <flux:text class="sp-card-subtitle mt-1">{{ __('Last 12 months') }}</flux:text>
        @php($revenueTitles = array_map(
            fn ($cents, $label) => $label.': € '.number_format($cents / 100, 2, ',', '.'),
            $this->revenuePerMonth['values'],
            $this->revenuePerMonth['labels'],
        ))
        @php($revenueDisplay = array_map(fn ($cents) => '€ '.number_format($cents / 100, 0, ',', '.'), $this->revenuePerMonth['values']))
        <x-charts.bars class="mt-4" :values="$this->revenuePerMonth['values']" :labels="$this->revenuePerMonth['labels']" :titles="$revenueTitles" :display="$revenueDisplay" />
    </x-portal.card>
</div>
