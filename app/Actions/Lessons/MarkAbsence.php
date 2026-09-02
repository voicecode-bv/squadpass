<?php

namespace App\Actions\Lessons;

use App\Exceptions\EnrollmentException;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use Illuminate\Support\Facades\DB;

class MarkAbsence
{
    public function handle(LessonEnrollment $enrollment, LessonOccurrence $occurrence): void
    {
        throw_unless($enrollment->isFixed(), EnrollmentException::notBookable());
        throw_unless($occurrence->lesson_id === $enrollment->lesson_id, EnrollmentException::notBookable());
        throw_if($occurrence->starts_at->isPast(), EnrollmentException::notBookable());

        DB::transaction(function () use ($enrollment, $occurrence): void {
            $created = $enrollment->absences()->firstOrCreate([
                'lesson_occurrence_id' => $occurrence->id,
            ])->wasRecentlyCreated;

            if ($created) {
                $occurrence->releaseSpot();

                if ($enrollment->credits_used > 0) {
                    $enrollment->decrement('credits_used');
                    $enrollment->subscription?->refundCredits(1);
                }
            }
        });
    }
}
