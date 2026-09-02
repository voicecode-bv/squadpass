<div class="flex w-full flex-col gap-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">{{ __('Tickets') }}</flux:heading>
            <flux:text class="mt-1">{{ $club->name }}</flux:text>
        </div>

        <flux:select wire:model.live="status" class="max-w-48">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (\App\Enums\TicketStatus::cases() as $statusOption)
                <flux:select.option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->tickets->isEmpty())
        <flux:callout icon="lifebuoy">
            <flux:callout.heading>{{ __('No tickets') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Tickets created by members will appear here.') }}</flux:callout.text>
        </flux:callout>
    @else
        <x-table-card>
            <flux:table :paginate="$this->tickets">
                <flux:table.columns>
                    <flux:table.column>{{ __('Ticket') }}</flux:table.column>
                    <flux:table.column>{{ __('Subject') }}</flux:table.column>
                    <flux:table.column>{{ __('Member') }}</flux:table.column>
                    <flux:table.column>{{ __('Category') }}</flux:table.column>
                    <flux:table.column>{{ __('Priority') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Assignee') }}</flux:table.column>
                    <flux:table.column>{{ __('Last update') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($this->tickets as $ticket)
                        <flux:table.row :key="$ticket->id">
                            <flux:table.cell variant="strong">
                                <flux:link :href="route('clubs.tickets.show', [$club, $ticket])" wire:navigate class="font-mono">{{ $ticket->reference() }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:link :href="route('clubs.tickets.show', [$club, $ticket])" wire:navigate variant="ghost">{{ $ticket->subject }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell><x-user-chip :user="$ticket->user" /></flux:table.cell>
                            <flux:table.cell>{{ $ticket->category->label() }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$ticket->priority->color()">{{ $ticket->priority->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$ticket->status->color()">{{ $ticket->status->label() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($ticket->assignee)
                                    <x-user-chip :user="$ticket->assignee" />
                                @else
                                    {{ __('Unassigned') }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $ticket->updated_at->timezone($club->timezone)->isoFormat('D MMM, HH:mm') }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-table-card>
    @endif
</div>
