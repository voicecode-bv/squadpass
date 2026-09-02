@props(['ticket', 'messages'])

<div class="flex flex-col gap-6 rounded-xl bg-white p-6 shadow-sm dark:bg-zinc-900">
    @foreach ($messages as $message)
        @php($fromMember = $message->user_id === $ticket->user_id)
        <div class="flex gap-3 {{ $fromMember ? '' : 'flex-row-reverse' }}" wire:key="message-{{ $message->id }}">
            <flux:avatar :src="$message->user->avatarUrl()" :name="$message->user->name" :initials="$message->user->initials()" size="sm" class="shrink-0" />
            <div class="flex max-w-[80%] flex-col gap-1.5 {{ $fromMember ? 'items-start' : 'items-end' }}">
                <div class="flex flex-col {{ $fromMember ? 'items-start' : 'items-end' }}">
                    <span class="text-sm font-semibold text-zinc-800 dark:text-white">{{ $message->user->name }}</span>
                    <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $message->created_at->timezone($ticket->club->timezone)->isoFormat('D MMM YYYY, HH:mm') }}</span>
                </div>
                <div class="rounded-xl px-4 py-3 text-sm leading-relaxed whitespace-pre-line {{ $fromMember ? 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200' : 'bg-blue-50 text-zinc-700 dark:bg-blue-500/10 dark:text-zinc-200' }}">{{ trim($message->body) }}</div>
            </div>
        </div>
    @endforeach
</div>
