<?php

namespace App\Livewire\Tickets;

use App\Enums\TicketStatus;
use App\Models\Club;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Tickets')]
class Index extends Component
{
    use WithPagination;

    public Club $club;

    #[Url]
    public string $status = '';

    public function mount(Club $club): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Ticket>
     */
    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        return $this->club->tickets()
            ->when(TicketStatus::tryFrom($this->status), fn ($query, TicketStatus $status) => $query->where('status', $status))
            ->with(['user', 'assignee'])
            ->latest('updated_at')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.tickets.index');
    }
}
