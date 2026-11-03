@props([
    'tickets',
    'limit',
])

{{-- Top bar bell: support tickets with a message the user has not read yet. --}}
@php
    $unreadCount = $tickets->count();
    $badge = $unreadCount > $limit ? $limit.'+' : (string) $unreadCount;
    $user = auth()->user();
@endphp

<flux:dropdown position="bottom" align="end" class="shrink-0" data-test="unread-tickets">
    <flux:button
        variant="ghost"
        square
        class="relative"
        :aria-label="$unreadCount > 0 ? trans_choice(':count unread ticket|:count unread tickets', $unreadCount) : __('No unread messages')"
    >
        <flux:icon.bell variant="mini" />
        @if ($unreadCount > 0)
            <span class="absolute -end-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[0.625rem] leading-none font-semibold text-white tabular-nums" data-test="unread-tickets-badge">{{ $badge }}</span>
        @endif
    </flux:button>

    <flux:menu class="w-80 max-w-[calc(100vw-2rem)]">
        <p class="px-2 pt-1.5 pb-2 text-xs font-semibold tracking-wider text-zinc-400 uppercase dark:text-zinc-500">{{ __('Unread messages') }}</p>

        @forelse ($tickets->take($limit) as $ticket)
            <flux:menu.item
                :href="$ticket->opensInPortalFor($user) ? route('portal.tickets.show', $ticket) : route('clubs.tickets.show', [$ticket->club, $ticket])"
                wire:navigate
                wire:key="unread-ticket-{{ $ticket->id }}"
            >
                <span class="flex min-w-0 flex-col py-0.5">
                    <span class="sp-strong truncate text-sm">{{ $ticket->subject }}</span>
                    <span class="sp-muted truncate text-xs">
                        {{ $ticket->reference() }} · {{ $ticket->club->name }} · {{ $ticket->last_message_at->diffForHumans() }}
                    </span>
                </span>
            </flux:menu.item>
        @empty
            <p class="sp-muted px-2 pb-2 text-sm">{{ __('No unread messages') }}</p>
        @endforelse
    </flux:menu>
</flux:dropdown>
