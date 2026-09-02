<?php

namespace App\Livewire\Portal;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Support ticket')]
class TicketShow extends Component
{
    public Ticket $ticket;

    public string $reply = '';

    public function mount(Ticket $ticket): void
    {
        $this->ticket = Ticket::query()
            ->where('user_id', Auth::id())
            ->with(['club', 'assignee'])
            ->findOrFail($ticket->id);
    }

    public function sendMessage(): void
    {
        $ticket = Ticket::query()
            ->where('user_id', Auth::id())
            ->findOrFail($this->ticket->id);

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

        $ticket->touch();

        $this->reply = '';

        unset($this->messages);
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

    public function render(): View
    {
        return view('livewire.portal.ticket-show');
    }
}
