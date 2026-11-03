<div class="flex w-full flex-col gap-6">
    <x-portal.hero :title="$lesson ? __('Edit lesson') : __('New lesson')" :subtitle="$club->name" />

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="name" :label="__('Name')" required />

        <flux:textarea wire:model="description" :label="__('Description')" rows="3" />

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:select wire:model="room_id" :label="__('Room')">
                <flux:select.option value="">{{ __('No room') }}</flux:select.option>
                @foreach ($this->availableRooms as $room)
                    <flux:select.option value="{{ $room->id }}">{{ $room->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="capacity" :label="__('Capacity')" type="number" min="1" required />
        </div>

        <div class="flex flex-col gap-3">
            <flux:label>{{ __('Weekly time slots') }}</flux:label>

            @foreach ($schedules as $index => $schedule)
                <div class="flex items-end gap-3" wire:key="schedule-{{ $index }}">
                    <div class="grid flex-1 gap-3 sm:grid-cols-3">
                        <flux:select wire:model="schedules.{{ $index }}.day_of_week" :label="__('Day of week')" :label:sr-only="! $loop->first">
                            @foreach ($this->dayOptions as $value => $label)
                                <flux:select.option value="{{ $value }}">{{ ucfirst($label) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="schedules.{{ $index }}.start_time" :label="__('Start time')" :label:sr-only="! $loop->first" type="time" required />
                        <flux:input wire:model="schedules.{{ $index }}.duration_minutes" :label="__('Duration (minutes)')" :label:sr-only="! $loop->first" type="number" min="15" step="15" required />
                    </div>

                    @unless ($lesson)
                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="trash"
                            wire:click="removeSchedule({{ $index }})"
                            :disabled="count($schedules) <= 1"
                            :aria-label="__('Remove time slot')"
                        />
                    @endunless
                </div>
            @endforeach

            @error('schedules')
                <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror

            @unless ($lesson)
                <div>
                    <flux:button variant="ghost" size="sm" icon="plus" wire:click="addSchedule">
                        {{ __('Add time slot') }}
                    </flux:button>
                </div>
                <flux:text class="text-sm">{{ __('Each time slot becomes its own weekly lesson with separate enrollments.') }}</flux:text>
            @endunless
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:input wire:model="starts_on" :label="__('First lesson from')" type="date" required />
            <flux:input wire:model="ends_on" :label="__('Ends on (optional)')" type="date" />
        </div>

        <flux:checkbox.group wire:model="trainer_ids" :label="__('Trainers')">
            @forelse ($this->availableTrainers as $trainer)
                <flux:checkbox value="{{ $trainer->id }}" :label="$trainer->user->name" />
            @empty
                <flux:text class="text-sm">{{ __('No active trainers yet. Add trainers first to assign them.') }}</flux:text>
            @endforelse
        </flux:checkbox.group>

        <flux:switch wire:model="active" :label="__('Active')" :description="__('Inactive lessons stop generating new occurrences.')" />

        @if ($club->trial_lessons_enabled)
            <div class="flex flex-col gap-4">
                <flux:switch wire:model.live="trial_enabled" :label="__('Offer as trial lesson')" :description="__('Visitors can book this lesson on the public trial lesson page.')" />

                @if ($trial_enabled)
                    <flux:input
                        wire:model="trial_price"
                        :label="__('Trial lesson price (€)')"
                        :description="$club->acceptsOnlinePayments() ? __('Leave empty for a free trial lesson. Paid trial lessons are paid online via Mollie.') : __('Leave empty for a free trial lesson. Paid trial lessons need online payments via Mollie.')"
                        type="number"
                        step="0.01"
                        min="0"
                    />
                @endif
            </div>
        @endif

        <div class="flex justify-between">
            <flux:button variant="ghost" :href="route('clubs.lessons', $club)" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ $lesson ? __('Save changes') : __('Create lesson') }}</flux:button>
        </div>
    </form>
</div>
