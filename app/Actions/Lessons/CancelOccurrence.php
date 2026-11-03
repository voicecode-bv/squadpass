<?php

namespace App\Actions\Lessons;

use App\Enums\EnrollmentType;
use App\Enums\LessonOccurrenceStatus;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelOccurrence
{
    public function __construct(
        protected CancelEnrollment $cancelEnrollment,
        protected MarkAbsence $markAbsence,
    ) {}

    /**
     * Cancel one lesson on behalf of the club. Everyone booked for an upcoming
     * lesson gets their spot released and their credit back, whatever the
     * cancellation deadline: one-time bookings are removed, recurring members
     * are marked absent for this lesson. Restoring the lesson does not rebook them.
     *
     * @return int number of members whose booking was released
     */
    public function handle(LessonOccurrence $occurrence, User $actor): int
    {
        return DB::transaction(function () use ($occurrence, $actor): int {
            $occurrence = LessonOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();

            if ($occurrence->isCancelled()) {
                return 0;
            }

            $occurrence->update(['status' => LessonOccurrenceStatus::Cancelled]);

            if ($occurrence->starts_at->isPast()) {
                return 0;
            }

            $released = 0;

            // At most one lesson's capacity of rows; loaded up front because cancelling deletes them.

            LessonEnrollment::query()
                ->where('lesson_occurrence_id', $occurrence->id)
                ->with(['occurrence', 'subscription'])
                ->get()
                ->each(function (LessonEnrollment $enrollment) use ($actor, &$released): void {
                    $this->cancelEnrollment->handle($enrollment, $actor);
                    $released++;
                });

            LessonEnrollment::query()
                ->where('lesson_id', $occurrence->lesson_id)
                ->where('type', EnrollmentType::Fixed)
                ->whereDoesntHave('absences', fn ($query) => $query->where('lesson_occurrence_id', $occurrence->id))
                ->with('subscription')
                ->get()
                ->each(function (LessonEnrollment $enrollment) use ($occurrence, $actor, &$released): void {
                    $this->markAbsence->handle($enrollment, $occurrence, $actor);
                    $released++;
                });

            return $released;
        });
    }
}
