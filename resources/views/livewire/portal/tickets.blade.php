<div class="flex w-full flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Support tickets') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Questions about billing, lessons or anything else? We are happy to help.') }}</flux:text>
        </div>

        @if ($this->memberClubs->isNotEmpty())
            <flux:modal.trigger name="create-ticket">
                <flux:button variant="primary" icon="plus">{{ __('New ticket') }}</flux:button>
            </flux:modal.trigger>
        @endif
    </div>

    @if ($this->tickets->isEmpty())
        <flux:callout icon="lifebuoy">
            <flux:callout.heading>{{ __('No tickets yet') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Create a ticket and we will get back to you as soon as possible.') }}</flux:callout.text>
        </flux:callout>
    @else
        <x-table-card>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Ticket') }}</flux:table.column>
                    <flux:table.column>{{ __('Subject') }}</flux:table.column>
                    <flux:table.column>{{ __('Club') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Priority') }}</flux:table.column>
                    <flux:table.column>{{ __('Last update') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->tickets as $ticket)
                        <flux:table.row :key="$ticket->id">
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('portal.tickets.show', $ticket)" wire:navigate class="font-mono">{{ $ticket->reference() }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:link :href="route('portal.tickets.show', $ticket)" wire:navigate variant="ghost">{{ $ticket->subject }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ $ticket->club->name }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$ticket->status->color()">{{ $ticket->status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$ticket->priority->color()">{{ $ticket->priority->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $ticket->updated_at->timezone($ticket->club->timezone)->isoFormat('D MMM YYYY, HH:mm') }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-table-card>
    @endif

    <flux:modal name="create-ticket" class="md:w-112">
        <form wire:submit="createTicket" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('New ticket') }}</flux:heading>
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
