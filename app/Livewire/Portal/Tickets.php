<?php

namespace App\Livewire\Portal;

use App\Enums\MembershipStatus;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Club;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Support tickets')]
class Tickets extends Component
{
    public string $club_id = '';

    public string $category = TicketCategory::Other->value;

    public string $priority = TicketPriority::Normal->value;

    public string $subject = '';

    public string $message = '';

    public function mount(): void
    {
        $this->club_id = (string) ($this->memberClubs->first()?->id ?? '');
    }

    public function createTicket(): void
    {
        $validated = $this->validate([
            'club_id' => ['required', Rule::in($this->memberClubs->pluck('id')->map(fn (int $id) => (string) $id))],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $club = Club::query()->findOrFail((int) $validated['club_id']);

        $ticket = DB::transaction(function () use ($club, $validated): Ticket {
            $ticket = Ticket::create([
                'club_id' => $club->id,
                'user_id' => Auth::id(),
                'number' => $club->allocateTicketNumber(),
                'subject' => $validated['subject'],
                'category' => $validated['category'],
                'status' => TicketStatus::Open,
                'priority' => $validated['priority'],
            ]);

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => Auth::id(),
                'body' => $validated['message'],
            ]);

            return $ticket;
        });

        $this->redirectRoute('portal.tickets.show', ['ticket' => $ticket], navigate: true);
    }

    /**
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function tickets(): Collection
    {
        return Ticket::query()
            ->where('user_id', Auth::id())
            ->with(['club'])
            ->latest('updated_at')
            ->get();
    }

    /**
     * Clubs where the user has an active membership, selectable for new tickets.
     *
     * @return Collection<int, Club>
     */
    #[Computed]
    public function memberClubs(): Collection
    {
        return Auth::user()->memberClubs()
            ->wherePivot('status', MembershipStatus::Active->value)
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.portal.tickets');
    }
}
