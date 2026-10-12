<div class="flex w-full flex-col gap-6">
    {{-- Header --}}
    <div>
        <flux:button :href="route('clubs.tickets', $club)" wire:navigate variant="ghost" size="sm" icon="arrow-left">
            {{ __('Back to tickets') }}
        </flux:button>

        <div class="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <span class="font-mono text-xl font-semibold text-zinc-400 dark:text-zinc-500">{{ $ticket->reference() }}</span>
            <flux:heading size="xl">{{ $ticket->subject }}</flux:heading>
        </div>

        <div class="mt-3 flex gap-2">
            <flux:badge size="sm" :color="$ticket->status->color()">{{ $ticket->status->label() }}</flux:badge>
            <flux:badge size="sm" :color="$ticket->priority->color()">{{ $ticket->priority->label() }}</flux:badge>
        </div>
    </div>

    {{-- Ticket details and handling --}}
    <div class="flex flex-col gap-6 rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">{{ __('Member') }}</p>
                <div class="mt-2 flex items-center gap-2">
                    <flux:avatar :src="$ticket->user->avatarUrl()" :name="$ticket->user->name" :initials="$ticket->user->initials()" size="sm" />
                    <div class="min-w-0">
                        <p class="truncate font-normal text-zinc-800 dark:text-white">{{ $ticket->user->name }}</p>
                        <p class="truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $ticket->user->email }}</p>
                    </div>
                </div>
            </div>
            <div>
                <p class="text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">{{ __('Category') }}</p>
                <p class="mt-2 font-normal text-zinc-800 dark:text-white">{{ $ticket->category->label() }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">{{ __('Created') }}</p>
                <p class="mt-2 font-normal text-zinc-800 dark:text-white">{{ $ticket->created_at->timezone($club->timezone)->isoFormat('D MMM YYYY, HH:mm') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">{{ __('Last update') }}</p>
                <p class="mt-2 font-normal text-zinc-800 dark:text-white">{{ $ticket->updated_at->timezone($club->timezone)->isoFormat('D MMM YYYY, HH:mm') }}</p>
            </div>
        </div>

        <flux:separator />

        <div class="grid gap-6 sm:grid-cols-3">
            <flux:select wire:model.live="status" :label="__('Status')">
                @foreach (\App\Enums\TicketStatus::cases() as $statusOption)
                    <flux:select.option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="priority" :label="__('Priority')">
                @foreach (\App\Enums\TicketPriority::cases() as $priorityOption)
                    <flux:select.option value="{{ $priorityOption->value }}">{{ $priorityOption->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="assignedTo" :label="__('Assignee')">
                <flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>
                @foreach ($this->staffMembers as $staffMember)
                    <flux:select.option value="{{ $staffMember->id }}">{{ $staffMember->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Conversation --}}
    <x-tickets.thread :ticket="$ticket" :messages="$this->messages" />

    {{-- Reply --}}
    @if ($ticket->isClosed())
        <flux:callout icon="lock-closed">
            <flux:callout.heading>{{ __('This ticket is closed.') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Reopen the ticket to send a reply.') }}</flux:callout.text>
        </flux:callout>
    @else
        <form wire:submit="sendMessage" class="flex flex-col gap-4 rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
            <flux:textarea wire:model="reply" rows="4" :placeholder="__('Write your reply...')" />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Send reply') }}</flux:button>
            </div>
        </form>
    @endif
</div>
