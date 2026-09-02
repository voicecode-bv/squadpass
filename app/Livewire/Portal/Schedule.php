<?php

namespace App\Livewire\Portal;

use App\Actions\Lessons\CancelEnrollment;
use App\Actions\Lessons\EnrollFixed;
use App\Actions\Lessons\EnrollSingle;
use App\Actions\Lessons\MarkAbsence;
use App\Enums\EnrollmentType;
use App\Enums\LessonOccurrenceStatus;
use App\Enums\MembershipStatus;
use App\Exceptions\EnrollmentException;
use App\Models\EnrollmentAbsence;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Lesson schedule')]
class Schedule extends Component
{
    public function enrollSingle(int $occurrenceId, EnrollSingle $enrollSingle): void
    {
        $occurrence = $this->findOccurrence($occurrenceId);

        try {
            $enrollSingle->handle(Auth::user(), $occurrence);
        } catch (EnrollmentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->refreshLists();

        Flux::toast(variant: 'success', text: __('Enrolled for this lesson.'));
    }

    public function enrollFixed(int $occurrenceId, EnrollFixed $enrollFixed): void
    {
        $occurrence = $this->findOccurrence($occurrenceId);

        try {
            $enrollFixed->handle(Auth::user(), $occurrence->lesson);
        } catch (EnrollmentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->refreshLists();

        Flux::toast(variant: 'success', text: __('Enrolled recurring for this lesson.'));
    }

    public function cancelSingle(int $occurrenceId, CancelEnrollment $cancelEnrollment): void
    {
        $enrollment = LessonEnrollment::query()
            ->where('user_id', Auth::id())
            ->where('lesson_occurrence_id', $occurrenceId)
            ->firstOrFail();

        $cancelEnrollment->handle($enrollment);

        $this->refreshLists();

        Flux::toast(variant: 'success', text: __('Enrollment cancelled.'));
    }

    public function cancelFixed(int $occurrenceId, CancelEnrollment $cancelEnrollment): void
    {
        $occurrence = $this->findOccurrence($occurrenceId);

        $enrollment = LessonEnrollment::query()
            ->where('user_id', Auth::id())
            ->where('type', EnrollmentType::Fixed)
            ->where('lesson_id', $occurrence->lesson_id)
            ->firstOrFail();

        $cancelEnrollment->handle($enrollment);

        $this->refreshLists();

        Flux::toast(variant: 'success', text: __('Recurring enrollment cancelled.'));
    }

    public function markAbsent(int $occurrenceId, MarkAbsence $markAbsence): void
    {
        $occurrence = $this->findOccurrence($occurrenceId);

        $enrollment = LessonEnrollment::query()
            ->where('user_id', Auth::id())
            ->where('lesson_id', $occurrence->lesson_id)
            ->firstOrFail();

        try {
            $markAbsence->handle($enrollment, $occurrence);
        } catch (EnrollmentException $exception) {
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        }

        $this->refreshLists();

        Flux::toast(variant: 'success', text: __('Marked absent for this lesson.'));
    }

    /**
     * @return Collection<int, LessonOccurrence>
     */
    #[Computed]
    public function occurrences(): Collection
    {
        $clubIds = Auth::user()->memberships()
            ->where('status', MembershipStatus::Active)
            ->pluck('club_id');

        return LessonOccurrence::query()
            ->whereIn('club_id', $clubIds)
            ->where('status', LessonOccurrenceStatus::Scheduled)
            ->whereBetween('starts_at', [now(), now()->addWeeks(4)])
            ->with(['lesson.trainers.user', 'lesson.room', 'club'])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @return array<int, true>
     */
    #[Computed]
    public function singleEnrolledOccurrenceIds(): array
    {
        $ids = LessonEnrollment::query()
            ->where('user_id', Auth::id())
            ->where('type', EnrollmentType::Single)
            ->pluck('lesson_occurrence_id');

        return array_fill_keys($ids->map(fn ($id): int => (int) $id)->all(), true);
    }

    /**
     * @return array<int, true>
     */
    #[Computed]
    public function fixedEnrolledLessonIds(): array
    {
        $ids = LessonEnrollment::query()
            ->where('user_id', Auth::id())
            ->where('type', EnrollmentType::Fixed)
            ->pluck('lesson_id');

        return array_fill_keys($ids->map(fn ($id): int => (int) $id)->all(), true);
    }

    /**
     * @return array<int, true>
     */
    #[Computed]
    public function absentOccurrenceIds(): array
    {
        $ids = EnrollmentAbsence::query()
            ->whereHas('enrollment', fn ($query) => $query->where('user_id', Auth::id()))
            ->pluck('lesson_occurrence_id');

        return array_fill_keys($ids->map(fn ($id): int => (int) $id)->all(), true);
    }

    public function render(): View
    {
        return view('livewire.portal.schedule');
    }

    protected function findOccurrence(int $occurrenceId): LessonOccurrence
    {
        return LessonOccurrence::query()->with(['lesson', 'club'])->findOrFail($occurrenceId);
    }

    protected function refreshLists(): void
    {
        unset($this->occurrences, $this->singleEnrolledOccurrenceIds, $this->fixedEnrolledLessonIds, $this->absentOccurrenceIds);
    }
}
