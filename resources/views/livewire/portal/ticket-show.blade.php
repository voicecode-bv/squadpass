<div class="flex w-full flex-col gap-6">
    {{-- Header --}}
    <div>
        <flux:button :href="route('portal.tickets')" wire:navigate variant="ghost" size="sm" icon="arrow-left">
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

    {{-- Ticket details --}}
    <div class="grid gap-6 rounded-xl bg-white p-6 shadow-sm sm:grid-cols-2 lg:grid-cols-4 dark:bg-zinc-900">
        <div>
            <p class="flex items-center gap-1.5 text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">
                <flux:icon.building-storefront variant="micro" class="size-4" /> {{ __('Club') }}
            </p>
            <p class="mt-2 font-medium text-zinc-800 dark:text-white">{{ $ticket->club->name }}</p>
        </div>
        <div>
            <p class="flex items-center gap-1.5 text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">
                <flux:icon.user variant="micro" class="size-4" /> {{ __('Assignee') }}
            </p>
            <div class="mt-2 font-medium text-zinc-800 dark:text-white">
                @if ($ticket->assignee)
                    <x-user-chip :user="$ticket->assignee" />
                @else
                    {{ __('Unassigned') }}
                @endif
            </div>
        </div>
        <div>
            <p class="flex items-center gap-1.5 text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">
                <flux:icon.tag variant="micro" class="size-4" /> {{ __('Category') }}
            </p>
            <p class="mt-2 font-medium text-zinc-800 dark:text-white">{{ $ticket->category->label() }}</p>
        </div>
        <div>
            <p class="flex items-center gap-1.5 text-xs font-semibold tracking-widest text-zinc-400 uppercase dark:text-zinc-500">
                <flux:icon.calendar variant="micro" class="size-4" /> {{ __('Created') }}
            </p>
            <p class="mt-2 font-medium text-zinc-800 dark:text-white">{{ $ticket->created_at->timezone($ticket->club->timezone)->isoFormat('D MMM YYYY, HH:mm') }}</p>
        </div>
    </div>

    {{-- Conversation --}}
    <x-tickets.thread :ticket="$ticket" :messages="$this->messages" />

    {{-- Reply --}}
    @if ($ticket->isClosed())
        <flux:callout icon="lock-closed">
            <flux:callout.heading>{{ __('This ticket is closed.') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Create a new ticket if you have another question.') }}</flux:callout.text>
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
