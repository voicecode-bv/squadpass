<?php

namespace App\Livewire\Portal;

use App\Actions\Lessons\CancelEnrollment;
use App\Enums\EnrollmentType;
use App\Models\LessonEnrollment;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('My enrollments')]
class MyEnrollments extends Component
{
    public function cancel(int $enrollmentId, CancelEnrollment $cancelEnrollment): void
    {
        $enrollment = LessonEnrollment::query()
            ->where('user_id', Auth::id())
            ->findOrFail($enrollmentId);

        $cancelEnrollment->handle($enrollment);

        unset($this->fixedEnrollments, $this->singleEnrollments);

        Flux::toast(variant: 'success', text: __('Enrollment cancelled.'));
    }

    /**
     * @return Collection<int, LessonEnrollment>
     */
    #[Computed]
    public function fixedEnrollments(): Collection
    {
        return LessonEnrollment::query()
            ->where('user_id', Auth::id())
            ->where('type', EnrollmentType::Fixed)
            ->with(['lesson.club'])
            ->get();
    }

    /**
     * @return Collection<int, LessonEnrollment>
     */
    #[Computed]
    public function singleEnrollments(): Collection
    {
        return LessonEnrollment::query()
            ->where('user_id', Auth::id())
            ->where('type', EnrollmentType::Single)
            ->whereHas('occurrence', fn ($query) => $query->where('starts_at', '>=', now()))
            ->with(['occurrence.lesson', 'occurrence.club'])
            ->get()
            ->sortBy(fn (LessonEnrollment $enrollment) => $enrollment->occurrence->starts_at)
            ->values();
    }

    public function render(): View
    {
        return view('livewire.portal.my-enrollments');
    }
}
