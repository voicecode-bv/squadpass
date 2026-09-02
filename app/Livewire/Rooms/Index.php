<?php

namespace App\Livewire\Rooms;

use App\Models\Club;
use App\Models\Room;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Rooms')]
class Index extends Component
{
    public Club $club;

    public string $name = '';

    public string $description = '';

    public function mount(Club $club): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
    }

    public function createRoom(): void
    {
        $this->authorize('manage', $this->club);

        $validated = $this->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('rooms')->where('club_id', $this->club->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        Room::create([
            'club_id' => $this->club->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
        ]);

        $this->reset('name', 'description');

        Flux::modal('create-room')->close();
        Flux::toast(variant: 'success', text: __('Room created.'));
    }

    public function toggleActive(int $roomId): void
    {
        $this->authorize('manage', $this->club);

        $room = Room::query()
            ->where('club_id', $this->club->id)
            ->findOrFail($roomId);

        $room->update(['active' => ! $room->active]);
    }

    /**
     * @return Collection<int, Room>
     */
    #[Computed]
    public function rooms(): Collection
    {
        return Room::query()
            ->where('club_id', $this->club->id)
            ->withCount('lessons')
            ->orderBy('name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.rooms.index');
    }
}
