<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="__('Check-in desk')" :subtitle="$club->name">
        <x-slot:actions>
            <flux:button :href="route('clubs.check-ins.kiosk', $club)" target="_blank" icon="qr-code" size="sm">
                {{ __('Open QR screen') }}
            </flux:button>
        </x-slot:actions>
    </x-portal.hero>

    @php
        $lastCheckIn = $this->todayCheckIns->first();
    @endphp

    <x-portal.kpi-band :items="[
        ['label' => __('Checked in') . ' · ' . __('Today'), 'value' => $this->todayCheckInCount, 'countUp' => $this->todayCheckInCount],
        ['label' => __('Lessons') . ' · ' . __('Today'), 'value' => $this->todayOccurrences->count(), 'countUp' => $this->todayOccurrences->count()],
        ['label' => __('Last check-in'), 'value' => $lastCheckIn ? $lastCheckIn->checked_in_at->timezone($club->timezone)->format('H:i') : '—'],
    ]" />

    <div class="grid items-start gap-6 lg:grid-cols-5">
        <div class="flex flex-col gap-6 lg:col-span-3">
            {{-- Manual check-in: pick the lesson (or a free visit), then find the member. --}}
            <section class="sp-panel flex flex-col gap-5 p-5" data-test="check-in-panel">
                <div>
                    <h2 class="sp-card-title">{{ __('Check in a member') }}</h2>
                    <p class="sp-card-subtitle mt-1">{{ __('Choose the lesson, then search for the member') }}</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select wire:model.live="occurrenceId" :label="__('Lesson')">
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
                        autofocus
                    />
                </div>

                @if (mb_strlen($search) >= 2)
                    <div class="-mx-5 -mb-5 border-t border-zinc-100 dark:border-white/5">
                        @forelse ($this->members as $membership)
                            <div class="flex items-center justify-between gap-4 px-5 py-3 transition hover:bg-zinc-100 dark:hover:bg-white/5" wire:key="member-{{ $membership->id }}">
                                <div class="flex min-w-0 items-center gap-3">
                                    <flux:avatar size="sm" :src="$membership->user->avatarUrl()" :name="$membership->user->name" :initials="$membership->user->initials()" />
                                    <div class="min-w-0">
                                        <p class="sp-strong truncate text-sm">{{ $membership->user->name }}</p>
                                        <p class="sp-muted truncate text-xs">#{{ $membership->membership_number }} · {{ $membership->user->email }}</p>
                                    </div>
                                </div>
                                <flux:button variant="primary" size="sm" wire:click="checkIn({{ $membership->user_id }})" class="shrink-0">
                                    {{ __('Check in') }}
                                </flux:button>
                            </div>
                        @empty
                            <p class="sp-muted px-5 py-4 text-sm">{{ __('No active members found.') }}</p>
                        @endforelse
                    </div>
                @endif
            </section>

            {{-- Reads the rotating QR code from the member's app; always a free visit, so the lesson picker above does not apply. --}}
            <section
                class="sp-panel flex flex-col gap-4 p-5"
                x-data="spQrScanner({
                    unsupported: @js(__('This browser cannot use the camera.')),
                    denied: @js(__('No camera access. Allow it in your browser and try again.')),
                })"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="sp-card-title">{{ __('Scan member QR code') }}</h2>
                        <p class="sp-card-subtitle mt-1">{{ __('Registers a visit without a lesson') }}</p>
                    </div>

                    <flux:button size="sm" x-show="! active" x-on:click="start()">{{ __('Start camera') }}</flux:button>
                    <flux:button size="sm" variant="ghost" x-cloak x-show="active" x-on:click="stop()">{{ __('Stop') }}</flux:button>
                </div>

                <div x-cloak x-show="active" class="overflow-hidden rounded-xl bg-zinc-900">
                    <video x-ref="video" class="aspect-video w-full object-cover" playsinline muted></video>
                </div>

                <p x-cloak x-show="error" x-text="error" class="text-sm text-red-600 dark:text-red-400"></p>
            </section>
        </div>

        {{-- Today's arrivals, newest first. --}}
        <section class="sp-panel flex flex-col lg:col-span-2" data-test="todays-check-ins">
            <div class="p-5">
                <h2 class="sp-card-title">{{ __('Checked in today') }}</h2>
                <p class="sp-card-subtitle mt-1">{{ trans_choice(':count visit|:count visits', $this->todayCheckInCount) }}</p>
            </div>

            @if ($this->todayCheckIns->isEmpty())
                <p class="sp-muted border-t border-zinc-100 px-5 py-4 text-sm dark:border-white/5">{{ __('No check-ins yet today.') }}</p>
            @else
                <ol class="border-t border-zinc-100 dark:border-white/5">
                    @foreach ($this->todayCheckIns as $checkIn)
                        <li class="flex items-center gap-3 px-5 py-3 transition hover:bg-zinc-100 dark:hover:bg-white/5" wire:key="check-in-{{ $checkIn->id }}">
                            <span class="sp-muted w-10 shrink-0 text-xs tabular-nums">{{ $checkIn->checked_in_at->timezone($club->timezone)->format('H:i') }}</span>
                            <flux:avatar size="xs" :src="$checkIn->user->avatarUrl()" :name="$checkIn->user->name" :initials="$checkIn->user->initials()" />
                            <div class="min-w-0">
                                <p class="sp-strong truncate text-sm">{{ $checkIn->user->name }}</p>
                                <p class="sp-muted truncate text-xs">{{ $checkIn->occurrence?->lesson->name ?? __('Free visit') }} · {{ $checkIn->coverageLabel() }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
</div>
