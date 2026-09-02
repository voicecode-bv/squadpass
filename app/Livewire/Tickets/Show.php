<?php

namespace App\Livewire\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Club;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Ticket')]
class Show extends Component
{
    public Club $club;

    public Ticket $ticket;

    public string $reply = '';

    public string $status = '';

    public string $priority = '';

    public string $assignedTo = '';

    public function mount(Club $club, Ticket $ticket): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
        $this->ticket = $club->tickets()->with(['user', 'assignee'])->findOrFail($ticket->id);
        $this->status = $this->ticket->status->value;
        $this->priority = $this->ticket->priority->value;
        $this->assignedTo = (string) ($this->ticket->assigned_to ?? '');
    }

    public function sendMessage(): void
    {
        $this->authorize('manage', $this->club);

        $ticket = $this->club->tickets()->findOrFail($this->ticket->id);

        if ($ticket->isClosed()) {
            Flux::toast(variant: 'danger', text: __('This ticket is closed.'));

            return;
        }

        $validated = $this->validate([
            'reply' => ['required', 'string', 'max:5000'],
        ]);

        TicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => Auth::id(),
            'body' => $validated['reply'],
        ]);

        // A first staff reply picks up the ticket: assign it and move it along.
        $ticket->fill([
            'assigned_to' => $ticket->assigned_to ?? Auth::id(),
            'status' => $ticket->status === TicketStatus::Open ? TicketStatus::InProgress : $ticket->status,
        ]);
        $ticket->touch();

        $this->ticket = $ticket->load(['user', 'assignee']);
        $this->status = $ticket->status->value;
        $this->assignedTo = (string) $ticket->assigned_to;
        $this->reply = '';

        unset($this->messages);
    }

    public function updatedStatus(string $value): void
    {
        $this->authorize('manage', $this->club);

        $status = TicketStatus::tryFrom($value);
        abort_unless($status !== null, 422);

        $ticket = $this->club->tickets()->findOrFail($this->ticket->id);
        $ticket->update(['status' => $status]);

        $this->ticket = $ticket->load(['user', 'assignee']);

        Flux::toast(variant: 'success', text: __('Ticket updated.'));
    }

    public function updatedPriority(string $value): void
    {
        $this->authorize('manage', $this->club);

        $priority = TicketPriority::tryFrom($value);
        abort_unless($priority !== null, 422);

        $ticket = $this->club->tickets()->findOrFail($this->ticket->id);
        $ticket->update(['priority' => $priority]);

        $this->ticket = $ticket->load(['user', 'assignee']);

        Flux::toast(variant: 'success', text: __('Ticket updated.'));
    }

    public function updatedAssignedTo(string $value): void
    {
        $this->authorize('manage', $this->club);

        $assigneeId = $value === '' ? null : (int) $value;

        if ($assigneeId !== null) {
            abort_unless($this->club->staff()->whereKey($assigneeId)->exists(), 422);
        }

        $ticket = $this->club->tickets()->findOrFail($this->ticket->id);
        $ticket->update(['assigned_to' => $assigneeId]);

        $this->ticket = $ticket->load(['user', 'assignee']);

        Flux::toast(variant: 'success', text: __('Ticket updated.'));
    }

    /**
     * @return Collection<int, TicketMessage>
     */
    #[Computed]
    public function messages(): Collection
    {
        return $this->ticket->messages()
            ->with('user')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Staff members of this club, selectable as assignee.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function staffMembers(): Collection
    {
        return $this->club->staff()->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.tickets.show');
    }
}
