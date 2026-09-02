<?php

namespace App\Livewire\Lessons;

use App\Models\Club;
use App\Models\Lesson;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Lessons')]
class Index extends Component
{
    public Club $club;

    public function mount(Club $club): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
    }

    /**
     * @return Collection<int, Lesson>
     */
    #[Computed]
    public function lessons(): Collection
    {
        return Lesson::query()
            ->where('club_id', $this->club->id)
            ->with(['trainers.user', 'room'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.lessons.index');
    }
}
