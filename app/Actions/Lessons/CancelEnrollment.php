<?php

namespace App\Actions\Lessons;

use App\Enums\LessonOccurrenceStatus;
use App\Models\LessonEnrollment;
use Illuminate\Support\Facades\DB;

class CancelEnrollment
{
    public function handle(LessonEnrollment $enrollment): void
    {
        DB::transaction(function () use ($enrollment): void {
            $refundableSpots = 0;

            if ($enrollment->isFixed()) {
                $absentOccurrenceIds = $enrollment->absences()->pluck('lesson_occurrence_id');

                $futureOccurrences = $enrollment->lesson->occurrences()
                    ->where('starts_at', '>=', now())
                    ->where('status', LessonOccurrenceStatus::Scheduled)
                    ->whereNotIn('id', $absentOccurrenceIds)
                    ->get();

                $futureOccurrences->each->releaseSpot();
                $refundableSpots = $futureOccurrences->count();

                $enrollment->absences()->delete();
            } elseif ($enrollment->occurrence && $enrollment->occurrence->starts_at->isFuture()) {
                $enrollment->occurrence->releaseSpot();
                $refundableSpots = 1;
            }

            // Refund unused credits for the released future spots.
            if ($enrollment->credits_used > 0 && $refundableSpots > 0) {
                $enrollment->subscription?->refundCredits(min($enrollment->credits_used, $refundableSpots));
            }

            $enrollment->delete();
        });
    }
}
