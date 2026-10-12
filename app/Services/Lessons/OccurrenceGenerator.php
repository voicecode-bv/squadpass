<?php

namespace App\Services\Lessons;

use App\Enums\EnrollmentType;
use App\Enums\LessonOccurrenceStatus;
use App\Models\Lesson;
use App\Models\LessonEnrollment;
use App\Models\LessonOccurrence;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        $startTimes = $this->startTimesWithinWindow($lesson);

        if ($startTimes === []) {
            return 0;
        }

        $existing = LessonOccurrence::query()
            ->where('lesson_id', $lesson->id)
            ->whereIn('starts_at', $startTimes)
            ->pluck('starts_at')
            ->map(fn ($startsAt) => $startsAt->toDateTimeString())
            ->all();

        $missing = array_values(array_diff($startTimes, $existing));

        if ($missing === []) {
            return 0;
        }

        $now = now();

        $inserted = LessonOccurrence::query()->insertOrIgnore(array_map(fn (string $startsAt) => [
            'lesson_id' => $lesson->id,
            'club_id' => $lesson->club_id,
            'starts_at' => $startsAt,
            'ends_at' => Carbon::parse($startsAt)->addMinutes($lesson->duration_minutes)->toDateTimeString(),
            'status' => LessonOccurrenceStatus::Scheduled->value,
            'booked_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $missing));

        $this->bookFixedEnrollments($lesson, $missing);

        return $inserted;
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

    /**
     * Members with a recurring enrollment get their spot in every new
     * occurrence. A subscription with credits pays for that week the moment it
     * appears; run out of credits and the week is left unbooked, so the club
     * keeps the spot to sell. Members without a subscription keep their spot
     * and pay per visit at the desk.
     *
     * @param  array<int, string>  $startTimes
     */
    private function bookFixedEnrollments(Lesson $lesson, array $startTimes): void
    {
        $enrollments = $lesson->enrollments()
            ->where('type', EnrollmentType::Fixed)
            ->get();

        if ($enrollments->isEmpty()) {
            return;
        }

        $occurrences = LessonOccurrence::query()
            ->where('lesson_id', $lesson->id)
            ->whereIn('starts_at', $startTimes)
            ->where('status', LessonOccurrenceStatus::Scheduled)
            ->orderBy('starts_at')
            ->get();

        if ($occurrences->isEmpty()) {
            return;
        }

        // One lookup per member instead of per occurrence; consuming credits
        // keeps the cached instance in sync.
        $subscriptions = $enrollments->mapWithKeys(fn (LessonEnrollment $enrollment) => [
            $enrollment->user_id => Subscription::currentFor($lesson->club_id, $enrollment->user_id),
        ]);

        DB::transaction(function () use ($enrollments, $occurrences, $subscriptions): void {
            foreach ($enrollments as $enrollment) {
                $this->bookEnrollment($enrollment, $occurrences, $subscriptions->get($enrollment->user_id));
            }
        });
    }

    /**
     * @param  Collection<int, LessonOccurrence>  $occurrences
     */
    private function bookEnrollment(LessonEnrollment $enrollment, Collection $occurrences, ?Subscription $subscription): void
    {
        $usesCredits = $subscription !== null && ! $subscription->isUnlimited();
        $creditsSpent = 0;

        foreach ($occurrences as $occurrence) {
            if (! $occurrence->claimSpot()) {
                continue;
            }

            if ($usesCredits && ! $subscription->consumeCredits(1)) {
                $occurrence->releaseSpot();

                break;
            }

            $creditsSpent += $usesCredits ? 1 : 0;
        }

        if ($creditsSpent > 0) {
            $enrollment->increment('credits_used', $creditsSpent);
        }
    }

    /**
     * Wall-clock start times within the rolling window, as UTC datetime strings.
     *
     * @return array<int, string>
     */
    private function startTimesWithinWindow(Lesson $lesson): array
    {
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

        $startTimes = [];

        for (; $date->lte($lastDate); $date->addWeek()) {
            // Build the wall-clock time in the club's timezone so DST transitions keep the local start time intact.
            $startTimes[] = Carbon::parse($date->toDateString().' '.$lesson->start_time, $timezone)->utc()->toDateTimeString();
        }

        return $startTimes;
    }
}
