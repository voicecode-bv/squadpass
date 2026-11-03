<div class="flex w-full flex-col gap-6">
    @php
        $euro = fn (int|float $cents, int $decimals = 2): string => '€ '.number_format($cents / 100, $decimals, ',', '.');
        $members = $this->members;
        $churn = $this->churn;
        $trials = $this->trialConversion;
        $attendance = $this->lessonAttendance;
        $periodLabel = trans_choice('Last :count month|Last :count months', $months, ['count' => $months]);
    @endphp

    <x-portal.hero :title="__('Reports')" :subtitle="$club->name">
        <x-slot:actions>
            <flux:radio.group wire:model.live="months" variant="segmented" size="sm" :label="__('Period')" label:sr-only>
                @foreach (\App\Livewire\Clubs\Reports::PERIODS as $period)
                    <flux:radio :value="$period" :label="trans_choice(':count month|:count months', $period, ['count' => $period])" />
                @endforeach
            </flux:radio.group>
        </x-slot:actions>
    </x-portal.hero>

    <x-portal.kpi-band :items="array_values(array_filter([
        ['label' => __('Active members').' · '.__('Right now'), 'value' => end($members['active']) ?: 0, 'countUp' => end($members['active']) ?: 0],
        ['label' => __('New members').' · '.$periodLabel, 'value' => array_sum($members['new']), 'countUp' => array_sum($members['new'])],
        $this->canSeeRevenue ? ['label' => __('Monthly recurring revenue'), 'value' => $euro($this->monthlyRecurringRevenueCents)] : null,
        ['label' => __('Trial lessons converted').' · '.$periodLabel, 'value' => $trials['trials'] > 0 ? $trials['converted'].' / '.$trials['trials'] : '—'],
    ]))" />

    <div class="grid gap-4 lg:grid-cols-2">
        <x-portal.card :delay="280" class="p-5">
            <flux:heading class="sp-card-title">{{ __('Active members per month') }}</flux:heading>
            <flux:text class="sp-card-subtitle mt-1">{{ $periodLabel }}</flux:text>
            <x-charts.area class="-mx-5 -mb-5 mt-4" label-class="px-5 pb-5" :values="$members['active']" :labels="$members['labels']" svg-class="h-40" />
        </x-portal.card>

        <x-portal.card :delay="330" class="p-5">
            <flux:heading class="sp-card-title">{{ __('New and ended memberships') }}</flux:heading>
            <flux:text class="sp-card-subtitle mt-1">{{ $periodLabel }}</flux:text>
            @php
                $memberFlowTitles = array_map(
                    fn ($label, $new, $ended) => $label.': +'.$new.' / −'.$ended,
                    $members['labels'], $members['new'], $members['ended'],
                );
                $memberFlowDisplay = array_map(fn ($new, $ended) => '+'.$new.' / −'.$ended, $members['new'], $members['ended']);
            @endphp
            <x-charts.bars class="mt-4" :values="$members['new']" :labels="$members['labels']" :titles="$memberFlowTitles" :display="$memberFlowDisplay" />
        </x-portal.card>

        <x-portal.card :delay="380" class="p-5">
            <flux:heading class="sp-card-title">{{ __('Cancelled subscriptions') }}</flux:heading>
            <flux:text class="sp-card-subtitle mt-1">{{ __('Churn = cancellations ÷ subscriptions running at the start of the month') }}</flux:text>
            @php
                $churnTitles = array_map(
                    fn ($label, $count, $rate) => $label.': '.$count.' ('.number_format($rate, 1, ',', '.').'%)',
                    $churn['labels'], $churn['cancellations'], $churn['rates'],
                );
                $churnDisplay = array_map(fn ($rate) => number_format($rate, 1, ',', '.').'%', $churn['rates']);
            @endphp
            <x-charts.bars class="mt-4" :values="$churn['cancellations']" :labels="$churn['labels']" :titles="$churnTitles" :display="$churnDisplay" />
        </x-portal.card>

        @if ($this->canSeeRevenue)
            @php
                $revenue = $this->revenue;
                $revenueTitles = array_map(fn ($cents, $label) => $label.': '.$euro($cents), $revenue['total'], $revenue['labels']);
                $revenueDisplay = array_map(fn ($cents) => $euro($cents, 0), $revenue['total']);
            @endphp
            <x-portal.card :delay="430" class="p-5">
                <flux:heading class="sp-card-title">{{ __('Revenue per month') }}</flux:heading>
                <flux:text class="sp-card-subtitle mt-1">
                    {{ __('Subscriptions :subscriptions · Strip cards :stripCards · Trial lessons :trialLessons', [
                        'subscriptions' => $euro($revenue['subscriptions'], 0),
                        'stripCards' => $euro($revenue['strip_cards'], 0),
                        'trialLessons' => $euro($revenue['trial_lessons'], 0),
                    ]) }}
                </flux:text>
                <x-charts.bars class="mt-4" :values="$revenue['total']" :labels="$revenue['labels']" :titles="$revenueTitles" :display="$revenueDisplay" />
            </x-portal.card>
        @endif
    </div>

    <section class="flex flex-col gap-3">
        <div>
            <h2 class="sp-card-title">{{ __('Attendance per lesson') }}</h2>
            <p class="sp-card-subtitle">{{ __('Lessons held in the period. No-shows are bookings without a check-in, shown only when the club checks people in.') }}</p>
        </div>

        @if ($attendance === [])
            <x-portal.empty :heading="__('No lessons held yet')" :text="__('Figures appear here once lessons in this period have taken place.')" />
        @else
            <x-table-card>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Lesson') }}</flux:table.column>
                        <flux:table.column>{{ __('Lessons held') }}</flux:table.column>
                        <flux:table.column>{{ __('Occupancy') }}</flux:table.column>
                        <flux:table.column>{{ __('No-shows') }}</flux:table.column>
                        <flux:table.column>{{ __('Late cancellations') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($attendance as $row)
                            <flux:table.row :key="$row['lesson_id']">
                                <flux:table.cell class="sp-strong">{{ $row['name'] }}</flux:table.cell>
                                <flux:table.cell>{{ $row['occurrences'] }}</flux:table.cell>
                                <flux:table.cell>{{ number_format($row['occupancy'], 1, ',', '.') }}% <span class="sp-muted">({{ $row['booked'] }}/{{ $row['capacity'] }})</span></flux:table.cell>
                                <flux:table.cell>{{ $row['no_show_rate'] === null ? '—' : number_format($row['no_show_rate'], 1, ',', '.').'%' }}</flux:table.cell>
                                <flux:table.cell>{{ $row['late_cancellations'] }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </x-table-card>
        @endif
    </section>
</div>
