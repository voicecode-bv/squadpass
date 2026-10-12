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

    /** Set while editing an existing room; null means the form creates one. */
    public ?int $editingRoomId = null;

    public string $name = '';

    public string $description = '';

    public function mount(Club $club): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
    }

    /**
     * Open the form for a new room.
     */
    public function newRoom(): void
    {
        $this->authorize('manage', $this->club);

        $this->resetValidation();
        $this->reset('editingRoomId', 'name', 'description');

        Flux::modal('room')->show();
    }

    /**
     * Open the form for an existing room.
     */
    public function editRoom(int $roomId): void
    {
        $this->authorize('manage', $this->club);

        $room = $this->findRoom($roomId);

        $this->resetValidation();
        $this->editingRoomId = $room->id;
        $this->name = $room->name;
        $this->description = $room->description ?? '';

        Flux::modal('room')->show();
    }

    public function saveRoom(): void
    {
        $this->authorize('manage', $this->club);

        $room = $this->editingRoomId ? $this->findRoom($this->editingRoomId) : null;

        $validated = $this->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('rooms')->where('club_id', $this->club->id)->ignore($room?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?: null,
        ];

        if ($room) {
            $room->update($attributes);
        } else {
            Room::create(['club_id' => $this->club->id, ...$attributes]);
        }

        $this->reset('editingRoomId', 'name', 'description');

        Flux::modal('room')->close();
        Flux::toast(variant: 'success', text: $room ? __('Room updated.') : __('Room created.'));
    }

    public function toggleActive(int $roomId): void
    {
        $this->authorize('manage', $this->club);

        $room = $this->findRoom($roomId);

        $room->update(['active' => ! $room->active]);
    }

    /**
     * Rooms of other clubs are a 404, never an edit target.
     */
    private function findRoom(int $roomId): Room
    {
        return Room::query()
            ->where('club_id', $this->club->id)
            ->findOrFail($roomId);
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
