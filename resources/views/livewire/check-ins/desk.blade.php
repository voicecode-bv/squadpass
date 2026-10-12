<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Check-in desk')" :subtitle="$club->name">
        <x-slot:actions>
            <flux:button
                :href="route('clubs.check-ins.kiosk', $club)"
                target="_blank"
                icon="qr-code"
                size="sm"
            >
                {{ __('Open QR screen') }}
            </flux:button>
        </x-slot:actions>
    </x-portal.hero>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="flex flex-col gap-4">
            {{-- Reads the rotating QR code from the member's app; always a free visit, so the lesson picker below does not apply. --}}
            <div
                class="sp-panel flex flex-col gap-3 p-5"
                x-data="spQrScanner({
                    unsupported: @js(__('This browser cannot use the camera.')),
                    denied: @js(__('No camera access. Allow it in your browser and try again.')),
                })"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="sp-card-title">{{ __('Scan member QR code') }}</p>
                        <p class="sp-card-subtitle">{{ __('Registers a visit without a lesson') }}</p>
                    </div>

                    <flux:button size="sm" x-show="! active" x-on:click="start()">{{ __('Start camera') }}</flux:button>
                    <flux:button size="sm" variant="ghost" x-cloak x-show="active" x-on:click="stop()">{{ __('Stop') }}</flux:button>
                </div>

                <div x-cloak x-show="active" class="overflow-hidden rounded-xl bg-zinc-900">
                    <video x-ref="video" class="aspect-video w-full object-cover" playsinline muted></video>
                </div>

                <p x-cloak x-show="error" x-text="error" class="text-sm text-red-600 dark:text-red-400"></p>
            </div>

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
                                <flux:table.cell class="sp-strong">{{ $checkIn->user->name }}</flux:table.cell>
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
