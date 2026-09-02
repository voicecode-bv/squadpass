<div class="mx-auto flex w-full max-w-2xl flex-col gap-8">
    <div>
        <flux:heading size="xl">{{ __('Set up :club', ['club' => $club->name]) }}</flux:heading>
        <flux:text class="mt-1">{{ __('Step :current of :total', ['current' => $step, 'total' => \App\Livewire\Clubs\Onboarding::LAST_STEP]) }} — {{ $this->stepLabels[$step] }}</flux:text>
    </div>

    {{-- Progress --}}
    <div class="flex items-center gap-2">
        @foreach ($this->stepLabels as $number => $label)
            <div class="flex flex-1 flex-col items-center gap-1" wire:key="step-{{ $number }}">
                <div @class([
                    'h-1.5 w-full rounded-full',
                    'bg-blue-600 dark:bg-blue-500' => $number <= $step,
                    'bg-zinc-200 dark:bg-zinc-700' => $number > $step,
                ])></div>
                <flux:text class="text-xs {{ $number === $step ? 'font-semibold' : '' }}">{{ $label }}</flux:text>
            </div>
        @endforeach
    </div>

    @if ($step === 1)
        <div class="flex flex-col gap-4">
            <flux:text>{{ __('Add the rooms where lessons take place, such as courts, halls or studios.') }}</flux:text>

            <form wire:submit="addRoom" class="flex items-end gap-3">
                <div class="flex-1">
                    <flux:input wire:model="room_name" :label="__('Room name')" placeholder="Baan 1" />
                </div>
                <flux:button type="submit" icon="plus">{{ __('Add') }}</flux:button>
            </form>

            @if ($this->rooms->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->rooms as $room)
                        <flux:badge wire:key="room-{{ $room->id }}">{{ $room->name }}</flux:badge>
                    @endforeach
                </div>
            @endif
        </div>
    @elseif ($step === 2)
        <div class="flex flex-col gap-4">
            <flux:text>{{ __('Invite trainers by email. They accept the invitation with an existing or new account — you can also do this later.') }}</flux:text>

            <form wire:submit="inviteTrainer" class="flex items-end gap-3">
                <div class="flex-1">
                    <flux:input wire:model="trainer_email" :label="__('Email address')" type="email" />
                </div>
                <flux:button type="submit" icon="envelope">{{ __('Invite') }}</flux:button>
            </form>

            @if ($this->trainers->isNotEmpty() || $this->pendingInvitations->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->trainers as $trainer)
                        <flux:badge color="green" wire:key="trainer-{{ $trainer->id }}">{{ $trainer->user->name }}</flux:badge>
                    @endforeach
                    @foreach ($this->pendingInvitations as $invitation)
                        <flux:badge color="yellow" wire:key="invitation-{{ $invitation->id }}">{{ $invitation->email }} · {{ __('Pending') }}</flux:badge>
                    @endforeach
                </div>
            @endif
        </div>
    @elseif ($step === 3)
        <div class="flex flex-col gap-4">
            <flux:text>{{ __('Create your first weekly lesson. Upcoming lesson dates are generated automatically.') }}</flux:text>

            <form wire:submit="createLesson" class="flex flex-col gap-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:input wire:model="lesson_name" :label="__('Name')" placeholder="Squash beginners" />
                    <flux:select wire:model="lesson_room_id" :label="__('Room')">
                        <flux:select.option value="">{{ __('No room') }}</flux:select.option>
                        @foreach ($this->rooms as $room)
                            <flux:select.option value="{{ $room->id }}">{{ $room->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:input wire:model="lesson_capacity" :label="__('Capacity')" type="number" min="1" />
                </div>

                <flux:label>{{ __('Weekly time slots') }}</flux:label>

                @foreach ($lesson_schedules as $index => $schedule)
                    <div class="flex items-end gap-3" wire:key="lesson-schedule-{{ $index }}">
                        <div class="grid flex-1 gap-3 sm:grid-cols-3">
                            <flux:select wire:model="lesson_schedules.{{ $index }}.day_of_week" :label="__('Day of week')">
                                @foreach ($this->dayOptions as $value => $label)
                                    <flux:select.option value="{{ $value }}">{{ ucfirst($label) }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:input wire:model="lesson_schedules.{{ $index }}.start_time" :label="__('Start time')" type="time" />
                            <flux:input wire:model="lesson_schedules.{{ $index }}.duration_minutes" :label="__('Duration (minutes)')" type="number" min="15" step="15" />
                        </div>

                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="trash"
                            wire:click="removeLessonSchedule({{ $index }})"
                            :disabled="count($lesson_schedules) <= 1"
                            :aria-label="__('Remove time slot')"
                        />
                    </div>
                @endforeach

                <div class="flex items-center gap-3">
                    <flux:button variant="ghost" size="sm" icon="plus" wire:click="addLessonSchedule">
                        {{ __('Add time slot') }}
                    </flux:button>
                    <flux:text class="text-sm">{{ __('Each time slot becomes its own weekly lesson with separate enrollments.') }}</flux:text>
                </div>

                <div>
                    <flux:button type="submit" icon="plus">{{ __('Create lesson') }}</flux:button>
                </div>
            </form>

            @if ($this->lessons->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->lessons as $lesson)
                        <flux:badge wire:key="lesson-{{ $lesson->id }}">{{ $lesson->name }}</flux:badge>
                    @endforeach
                </div>
            @endif
        </div>
    @elseif ($step === 4)
        <div class="flex flex-col gap-6">
            <flux:text>{{ __('What can members buy? Add a subscription, a strip card, or both.') }}</flux:text>

            <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
                <flux:heading>{{ __('Subscription plan') }}</flux:heading>
                <form wire:submit="createSubscriptionPlan" class="flex flex-col gap-4">
                    <div class="grid gap-4 sm:grid-cols-4">
                        <flux:input wire:model="plan_name" :label="__('Name')" placeholder="Onbeperkt" />
                        <flux:input wire:model="plan_price" :label="__('Price (€)')" type="number" step="0.01" min="0" />
                        <flux:select wire:model="plan_interval" :label="__('Billing interval')">
                            @foreach (\App\Enums\BillingInterval::cases() as $intervalOption)
                                <flux:select.option value="{{ $intervalOption->value }}">{{ $intervalOption->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="plan_credits" :label="__('Credits (empty = unlimited)')" type="number" min="1" />
                    </div>
                    <div>
                        <flux:button type="submit" icon="plus" size="sm">{{ __('Add subscription plan') }}</flux:button>
                    </div>
                </form>
                @if ($this->subscriptionPlans->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->subscriptionPlans as $plan)
                            <flux:badge wire:key="plan-{{ $plan->id }}">{{ $plan->name }} · {{ $plan->formattedPrice() }}</flux:badge>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm dark:bg-zinc-900">
                <flux:heading>{{ __('Strip card') }}</flux:heading>
                <form wire:submit="createStripCardPlan" class="flex flex-col gap-4">
                    <div class="grid gap-4 sm:grid-cols-4">
                        <flux:input wire:model="card_name" :label="__('Name')" placeholder="10-strippenkaart" />
                        <flux:input wire:model="card_strips" :label="__('Number of strips')" type="number" min="1" />
                        <flux:input wire:model="card_price" :label="__('Price (€)')" type="number" step="0.01" min="0" />
                        <flux:input wire:model="card_validity_months" :label="__('Valid for (months)')" type="number" min="1" />
                    </div>
                    <div>
                        <flux:button type="submit" icon="plus" size="sm">{{ __('Add strip card plan') }}</flux:button>
                    </div>
                </form>
                @if ($this->stripCardPlans->isNotEmpty())
                    <div class="flex flex-wrap gap-2">
                        @foreach ($this->stripCardPlans as $plan)
                            <flux:badge wire:key="card-{{ $plan->id }}">{{ $plan->name }} · {{ $plan->formattedPrice() }}</flux:badge>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @elseif ($step === 5)
        @if ($club->hasAddon(\App\Enums\AddonKey::Mollie))
            <div class="flex flex-col gap-4">
                <flux:text>{{ __('Connect Mollie so members can pay online for subscriptions and strip cards. You can find your API key in the Mollie dashboard under Developers → API keys.') }}</flux:text>

                <form wire:submit="saveMollieKey" class="flex items-end gap-3">
                    <div class="flex-1">
                        <flux:input wire:model="mollie_api_key" :label="__('Mollie API key')" type="password" viewable placeholder="test_..." />
                    </div>
                    <flux:button type="submit">{{ __('Save') }}</flux:button>
                </form>

                @if (str_starts_with($mollie_api_key, 'live_'))
                    <flux:text class="text-sm">{{ __('Live mode: real payments will be processed.') }}</flux:text>
                @elseif (str_starts_with($mollie_api_key, 'test_'))
                    <flux:text class="text-sm">{{ __('Test mode: no real payments will be processed.') }}</flux:text>
                @endif
            </div>
        @else
            <flux:callout icon="credit-card" variant="secondary">
                <flux:callout.heading>{{ __('Online payments (Mollie)') }}</flux:callout.heading>
                <flux:callout.text>{{ __('Online payments are an add-on. Activate it to connect your Mollie account; you can also do this later from the add-ons page.') }}</flux:callout.text>
                <x-slot name="actions">
                    <flux:button :href="route('clubs.addons', $club)" wire:navigate size="sm">{{ __('View add-ons') }}</flux:button>
                </x-slot>
            </flux:callout>
        @endif
    @endif

    <flux:separator />

    <div class="flex items-center justify-between">
        <div>
            @if ($step > 1)
                <flux:button variant="ghost" wire:click="back" icon="arrow-left">{{ __('Back') }}</flux:button>
            @endif
        </div>

        <div class="flex items-center gap-3">
            @if ($step < \App\Livewire\Clubs\Onboarding::LAST_STEP)
                <flux:button variant="ghost" wire:click="next">{{ __('Skip for now') }}</flux:button>
                <flux:button variant="primary" wire:click="next" icon-trailing="arrow-right">{{ __('Next') }}</flux:button>
            @else
                <flux:button variant="primary" wire:click="finish" icon="check">{{ __('Finish setup') }}</flux:button>
            @endif
        </div>
    </div>
</div>
