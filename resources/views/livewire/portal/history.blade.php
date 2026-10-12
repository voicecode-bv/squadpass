<div class="flex w-full flex-col gap-6">
    <x-portal.hero
        :eyebrow="__('Your activity')"
        :title="__('My visits')"
        :subtitle="__('Every check-in at your clubs, newest first.')"
    >
        @if ($this->checkIns->total() > 0)
            <div class="grid gap-3 sm:grid-cols-2">
                <x-portal.stat :count-up="$this->checkIns->total()" :value="$this->checkIns->total()" :label="__('Total visits')" :delay="260" />
                <x-portal.stat :value="$this->checkIns->first()?->checked_in_at->timezone($this->checkIns->first()->club->timezone)->isoFormat('D MMM YYYY') ?? '—'" :label="__('Last visit')" :delay="320" />
            </div>
        @endif
    </x-portal.hero>

    @if ($this->checkIns->isEmpty())
        <x-portal.empty icon="finger-print" :heading="__('No visits yet')" :text="__('Your check-ins appear here as soon as you visit your club.')" />
    @else
        <div class="stagger animate-rise sp-panel overflow-hidden px-5 py-2" style="--d: 120ms">
            <flux:table :paginate="$this->checkIns">
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Club') }}</flux:table.column>
                    <flux:table.column>{{ __('Lesson') }}</flux:table.column>
                    <flux:table.column>{{ __('Coverage') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->checkIns as $checkInIndex => $checkIn)
                        <flux:table.row :key="$checkIn->id" class="stagger animate-fade group transition hover:bg-blue-50/60 dark:hover:bg-blue-500/5" style="--d: {{ min(500, $checkInIndex * 45) }}ms">
                            <flux:table.cell class="sp-strong">
                                {{ $checkIn->checked_in_at->timezone($checkIn->club->timezone)->isoFormat('D MMM YYYY HH:mm') }}
                            </flux:table.cell>
                            <flux:table.cell>{{ $checkIn->club->name }}</flux:table.cell>
                            <flux:table.cell>{{ $checkIn->occurrence?->lesson->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm">{{ $checkIn->coverageLabel() }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    @endif
</div>
