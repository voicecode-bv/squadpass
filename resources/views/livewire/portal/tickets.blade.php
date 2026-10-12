<div class="flex w-full flex-col gap-6">
    @php($openTickets = $this->tickets->reject(fn ($ticket) => $ticket->isClosed()))

    <x-portal.hero
        :eyebrow="__('Support')"
        :title="__('Support tickets')"
        :subtitle="__('Questions about billing, lessons or anything else? We are happy to help.')"
    >
        <x-slot:actions>
            @if ($this->memberClubs->isNotEmpty())
                <flux:modal.trigger name="create-ticket">
                    <flux:button variant="primary" icon="plus"
                                >
                        {{ __('New ticket') }}
                    </flux:button>
                </flux:modal.trigger>
            @endif
        </x-slot:actions>

        @if ($this->tickets->isNotEmpty())
            <div class="grid gap-3 sm:grid-cols-2">
                <x-portal.stat :count-up="$openTickets->count()" :value="$openTickets->count()" :label="__('Open tickets')" :delay="260" />
                <x-portal.stat :count-up="$this->tickets->count() - $openTickets->count()" :value="$this->tickets->count() - $openTickets->count()" :label="__('Closed tickets')" :delay="320" />
            </div>
        @endif
    </x-portal.hero>

    @if ($this->tickets->isEmpty())
        <x-portal.empty icon="lifebuoy" :heading="__('No tickets yet')" :text="__('Create a ticket and we will get back to you as soon as possible.')">
            @if ($this->memberClubs->isNotEmpty())
                <flux:modal.trigger name="create-ticket">
                    <flux:button variant="primary" icon="plus"
                                >
                        {{ __('New ticket') }}
                    </flux:button>
                </flux:modal.trigger>
            @endif
        </x-portal.empty>
    @else
        <div class="flex flex-col gap-3">
            @foreach ($this->tickets as $ticketIndex => $ticket)
                <x-portal.card :delay="min(600, $ticketIndex * 60)" wire:key="ticket-{{ $ticket->id }}" class="p-4">
                    <span class="absolute inset-y-0 left-0 w-1 {{ $ticket->isClosed() ? 'bg-zinc-300 dark:bg-zinc-700' : 'bg-blue-600' }}" aria-hidden="true"></span>

                    <a href="{{ route('portal.tickets.show', $ticket) }}" wire:navigate class="relative flex flex-col gap-3 pl-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-semibold text-zinc-400 dark:text-zinc-500">{{ $ticket->reference() }}</span>
                                <span class="truncate text-xs text-zinc-400 dark:text-zinc-500">{{ $ticket->club->name }}</span>
                            </div>
                            <flux:heading class="sp-card-title truncate transition group-hover:text-blue-600 dark:group-hover:text-blue-400">{{ $ticket->subject }}</flux:heading>
                            <flux:text class="text-xs">
                                {{ __('Last update') }}: {{ $ticket->updated_at->timezone($ticket->club->timezone)->isoFormat('D MMM YYYY, HH:mm') }}
                            </flux:text>
                        </div>

                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <flux:badge size="sm" :color="$ticket->status->color()">{{ $ticket->status->label() }}</flux:badge>
                            <flux:badge size="sm" :color="$ticket->priority->color()">{{ $ticket->priority->label() }}</flux:badge>
                            <flux:icon.arrow-right class="size-4 text-zinc-300 transition duration-300 group-hover:translate-x-1 group-hover:text-blue-500 dark:text-zinc-600" />
                        </div>
                    </a>
                </x-portal.card>
            @endforeach
        </div>
    @endif

    <flux:modal name="create-ticket" class="md:w-112">
        <form wire:submit="createTicket" class="space-y-6">
            <div>
                <flux:heading class="sp-card-title">{{ __('New ticket') }}</flux:heading>
                <flux:text class="mt-2">{{ __('Describe your question and we will get back to you.') }}</flux:text>
            </div>

            @if ($this->memberClubs->count() > 1)
                <flux:select wire:model="club_id" :label="__('Club')">
                    @foreach ($this->memberClubs as $club)
                        <flux:select.option value="{{ $club->id }}">{{ $club->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <div class="grid gap-6 sm:grid-cols-2">
                <flux:select wire:model="category" :label="__('Category')">
                    @foreach (\App\Enums\TicketCategory::cases() as $category)
                        <flux:select.option value="{{ $category->value }}">{{ $category->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="priority" :label="__('Priority')">
                    @foreach (\App\Enums\TicketPriority::cases() as $priority)
                        <flux:select.option value="{{ $priority->value }}">{{ $priority->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:input wire:model="subject" :label="__('Subject')" required />

            <flux:textarea wire:model="message" :label="__('Message')" rows="5" required />

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Create ticket') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
