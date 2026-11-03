<?php

namespace App\Livewire\Lessons;

use App\Actions\Lessons\CancelOccurrence;
use App\Enums\LessonOccurrenceStatus;
use App\Models\Club;
use App\Models\LessonOccurrence;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Schedule')]
class Schedule extends Component
{
    use WithPagination;

    public Club $club;

    public function mount(Club $club): void
    {
        $this->authorize('manage', $club);

        $this->club = $club;
    }

    public function cancelOccurrence(int $occurrenceId, CancelOccurrence $cancelOccurrence): void
    {
        $this->authorize('manage', $this->club);

        $occurrence = LessonOccurrence::query()
            ->where('club_id', $this->club->id)
            ->findOrFail($occurrenceId);

        $released = $cancelOccurrence->handle($occurrence, Auth::user());

        Flux::toast(variant: 'success', text: __('Lesson occurrence cancelled.').($released > 0
            ? ' '.trans_choice(':count booking was released and its credit refunded.|:count bookings were released and their credits refunded.', $released)
            : ''));
    }

    public function restoreOccurrence(int $occurrenceId): void
    {
        $this->authorize('manage', $this->club);

        $occurrence = LessonOccurrence::query()
            ->where('club_id', $this->club->id)
            ->findOrFail($occurrenceId);

        $occurrence->update(['status' => LessonOccurrenceStatus::Scheduled]);

        Flux::toast(variant: 'success', text: __('Lesson occurrence restored.'));
    }

    /**
     * @return LengthAwarePaginator<int, LessonOccurrence>
     */
    #[Computed]
    public function occurrences(): LengthAwarePaginator
    {
        return LessonOccurrence::query()
            ->where('club_id', $this->club->id)
            ->where('starts_at', '>=', now())
            ->with('lesson')
            ->orderBy('starts_at')
            ->paginate(25);
    }

    public function render(): View
    {
        return view('livewire.lessons.schedule');
    }
}
