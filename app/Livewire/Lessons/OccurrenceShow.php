<?php

namespace App\Livewire\Lessons;

use App\Actions\Lessons\CancelEnrollment;
use App\Actions\Lessons\MarkAbsence;
use App\Exceptions\EnrollmentException;
use App\Models\Club;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Lesson participants')]
class OccurrenceShow extends Component
{
    public Club $club;

    public LessonOccurrence $occurrence;

    public function mount(Club $club, LessonOccurrence $occurrence): void
    {
        $this->authorize('manage', $club);
        abort_unless($occurrence->club_id === $club->id, 404);

        $this->club = $club;
        $this->occurrence = $occurrence;
    }

    public function removeEnrollment(int $enrollmentId, CancelEnrollment $cancelEnrollment): void
    {
        $this->authorize('manage', $this->club);

        $enrollment = LessonEnrollment::query()
            ->where('club_id', $this->club->id)
            ->findOrFail($enrollmentId);

        $cancelEnrollment->handle($enrollment);

        unset($this->participants);
        $this->occurrence->refresh();

        Flux::toast(variant: 'success', text: __('Enrollment cancelled.'));
    }

    public function markAbsent(int $enrollmentId, MarkAbsence $markAbsence): void
    {
        $this->authorize('manage', $this->club);

        $enrollment = LessonEnrollment::query()
            ->where('club_id', $this->club->id)
            ->findOrFail($enrollmentId);

        try {
            $markAbsence->handle($enrollment, $this->occurrence);
        } catch (EnrollmentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        unset($this->participants);
        $this->occurrence->refresh();

        Flux::toast(variant: 'success', text: __('Marked absent for this occurrence.'));
    }

    /**
     * @return Collection<int, LessonEnrollment>
     */
    #[Computed]
    public function participants(): Collection
    {
        return $this->occurrence->participants();
    }

    public function render(): View
    {
        return view('livewire.lessons.occurrence-show');
    }
}
