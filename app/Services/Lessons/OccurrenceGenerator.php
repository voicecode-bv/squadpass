<?php

namespace App\Services\Lessons;

use App\Enums\EnrollmentType;
use App\Enums\LessonOccurrenceStatus;
use App\Models\Lesson;
use App\Models\LessonOccurrence;
use Illuminate\Support\Carbon;

class OccurrenceGenerator
{
    public const int WEEKS_AHEAD = 8;

    /**
     * Generate missing occurrences for a lesson within the rolling window.
     *
     * Idempotent: existing occurrences (including cancelled ones) are left
     * untouched via the unique (lesson_id, starts_at) constraint.
     */
    public function generateForLesson(Lesson $lesson): int
    {
        if (! $lesson->active) {
            return 0;
        }

        $timezone = $lesson->club->timezone;
        $today = Carbon::today($timezone);
        $windowEnd = $today->copy()->addWeeks(self::WEEKS_AHEAD);

        $date = Carbon::parse($lesson->starts_on->toDateString(), $timezone);

        if ($date->lt($today)) {
            $date = $today->copy();
        }

        while ($date->isoWeekday() !== $lesson->day_of_week) {
            $date->addDay();
        }

        $lastDate = $lesson->ends_on
            ? Carbon::parse($lesson->ends_on->toDateString(), $timezone)->min($windowEnd)
            : $windowEnd;

        $rows = [];
        $now = now();

        // Fixed enrollments occupy a spot in every newly generated occurrence.
        $fixedEnrollmentCount = $lesson->enrollments()->where('type', EnrollmentType::Fixed)->count();

        for (; $date->lte($lastDate); $date->addWeek()) {
            // Build the wall-clock time in the club's timezone so DST transitions keep the local start time intact.
            $startsAt = Carbon::parse($date->toDateString().' '.$lesson->start_time, $timezone)->utc();

            $rows[] = [
                'lesson_id' => $lesson->id,
                'club_id' => $lesson->club_id,
                'starts_at' => $startsAt->toDateTimeString(),
                'ends_at' => $startsAt->copy()->addMinutes($lesson->duration_minutes)->toDateTimeString(),
                'status' => LessonOccurrenceStatus::Scheduled->value,
                'booked_count' => $fixedEnrollmentCount,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        return LessonOccurrence::query()->insertOrIgnore($rows);
    }

    /**
     * Rebuild future occurrences after a schedule change, preserving any
     * occurrence that already has bookings.
     */
    public function resyncFutureOccurrences(Lesson $lesson): int
    {
        LessonOccurrence::query()
            ->where('lesson_id', $lesson->id)
            ->where('starts_at', '>=', now())
            ->where('booked_count', 0)
            ->delete();

        return $this->generateForLesson($lesson);
    }
}
