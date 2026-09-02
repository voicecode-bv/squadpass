<div class="flex w-full flex-col gap-6">
    <div>
        <flux:heading size="xl">{{ __('Check-in desk') }}</flux:heading>
        <flux:text class="mt-1">{{ $club->name }}</flux:text>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="flex flex-col gap-4">
            <flux:select wire:model.live="occurrenceId" :label="__('Lesson (optional)')">
                <flux:select.option value="">{{ __('Free visit (no lesson)') }}</flux:select.option>
                @foreach ($this->todayOccurrences as $occurrence)
                    <flux:select.option value="{{ $occurrence->id }}">
                        {{ $occurrence->starts_at->timezone($club->timezone)->format('H:i') }} — {{ $occurrence->lesson->name }}
                    </flux:select.option>
                @endforeach
            </flux:select>

            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :label="__('Member')"
                :placeholder="__('Search by name or email...')"
                clearable
            />

            <div class="flex flex-col gap-2">
                @forelse ($this->members as $membership)
                    <div class="flex items-center justify-between rounded-lg bg-white px-4 py-3 shadow-sm dark:bg-zinc-900" wire:key="member-{{ $membership->id }}">
                        <div class="flex items-center gap-3">
                            <flux:avatar size="sm" :src="$membership->user->avatarUrl()" :name="$membership->user->name" :initials="$membership->user->initials()" />
                            <div>
                                <flux:heading>{{ $membership->user->name }}</flux:heading>
                                <flux:text class="text-sm">#{{ $membership->membership_number }} · {{ $membership->user->email }}</flux:text>
                            </div>
                        </div>
                        <flux:button variant="primary" size="sm" wire:click="checkIn({{ $membership->user_id }})">
                            {{ __('Check in') }}
                        </flux:button>
                    </div>
                @empty
                    @if (mb_strlen($search) >= 2)
                        <flux:text>{{ __('No active members found.') }}</flux:text>
                    @endif
                @endforelse
            </div>
        </div>

        <div class="flex flex-col gap-4">
            <flux:heading size="lg">{{ __('Checked in today') }}</flux:heading>

            @if ($this->todayCheckIns->isEmpty())
                <flux:text>{{ __('No check-ins yet today.') }}</flux:text>
            @else
                <x-table-card>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Time') }}</flux:table.column>
                        <flux:table.column>{{ __('Member') }}</flux:table.column>
                        <flux:table.column>{{ __('Lesson') }}</flux:table.column>
                        <flux:table.column>{{ __('Coverage') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->todayCheckIns as $checkIn)
                            <flux:table.row :key="$checkIn->id">
                                <flux:table.cell>{{ $checkIn->checked_in_at->timezone($club->timezone)->format('H:i') }}</flux:table.cell>
                                <flux:table.cell variant="strong"><x-user-chip :user="$checkIn->user" /></flux:table.cell>
                                <flux:table.cell>{{ $checkIn->occurrence?->lesson->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm">{{ $checkIn->coverageLabel() }}</flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
                </x-table-card>
            @endif
        </div>
    </div>
</div>
