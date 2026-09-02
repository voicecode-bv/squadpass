<?php

namespace App\Livewire\Trainers;

use App\Enums\LessonOccurrenceStatus;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('My lessons')]
class MyLessons extends Component
{
    public ?int $selectedOccurrenceId = null;

    public function selectOccurrence(int $occurrenceId): void
    {
        // Only occurrences of lessons this trainer is assigned to are viewable.
        $this->selectedOccurrenceId = $this->occurrenceQuery()
            ->whereKey($occurrenceId)
            ->firstOrFail()
            ->id;
    }

    /**
     * @return Collection<int, LessonOccurrence>
     */
    #[Computed]
    public function occurrences(): Collection
    {
        return $this->occurrenceQuery()
            ->whereBetween('starts_at', [now(), now()->addWeeks(4)])
            ->where('status', LessonOccurrenceStatus::Scheduled)
            ->with(['lesson.club', 'lesson.room'])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @return Collection<int, LessonEnrollment>
     */
    #[Computed]
    public function participants(): Collection
    {
        if ($this->selectedOccurrenceId === null) {
            return new Collection;
        }

        return $this->occurrenceQuery()
            ->whereKey($this->selectedOccurrenceId)
            ->firstOrFail()
            ->participants();
    }

    public function render(): View
    {
        return view('livewire.trainers.my-lessons');
    }

    /**
     * @return Builder<LessonOccurrence>
     */
    protected function occurrenceQuery(): Builder
    {
        return LessonOccurrence::query()
            ->whereHas('lesson.trainers', fn ($query) => $query->where('user_id', Auth::id()));
    }
}
