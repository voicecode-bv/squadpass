@props(['ticket', 'messages'])

<div {{ $attributes->class('sp-panel flex flex-col gap-6 p-6') }}>

    @foreach ($messages as $messageIndex => $message)
        @php($fromMember = $message->user_id === $ticket->user_id)

        <div class="stagger animate-rise relative flex gap-3 {{ $fromMember ? '' : 'flex-row-reverse' }}"
             style="--d: {{ min(600, $messageIndex * 70) }}ms"
             wire:key="message-{{ $message->id }}">
            <flux:avatar :src="$message->user->avatarUrl()" :name="$message->user->name" :initials="$message->user->initials()" size="sm" class="shrink-0 ring-2 ring-white shadow-sm dark:ring-zinc-800" />
            <div class="flex max-w-[80%] flex-col gap-1.5 {{ $fromMember ? 'items-start' : 'items-end' }}">
                <div class="flex flex-col {{ $fromMember ? 'items-start' : 'items-end' }}">
                    <span class="text-sm font-semibold text-zinc-800 dark:text-white">{{ $message->user->name }}</span>
                    <span class="text-xs text-zinc-400 dark:text-zinc-500">{{ $message->created_at->timezone($ticket->club->timezone)->isoFormat('D MMM YYYY, HH:mm') }}</span>
                </div>
                <div class="rounded-2xl px-4 py-3 text-sm leading-relaxed whitespace-pre-line shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md {{ $fromMember ? 'rounded-tl-sm bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-200' : 'rounded-tr-sm bg-blue-600 text-white shadow-blue-500/25' }}">{{ trim($message->body) }}</div>
            </div>
        </div>
    @endforeach
</div>
