<?php

namespace App\Services;

use App\Enums\BillingInterval;
use App\Enums\LessonOccurrenceStatus;
use App\Enums\MembershipStatus;
use App\Enums\SubscriptionStatus;
use App\Models\CheckIn;
use App\Models\Club;
use App\Models\LessonOccurrence;
use App\Models\Membership;
use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Snapshot and per-lesson figures for the club reports page. Every query is
 * scoped to one club and bounded by the period, so it stays index-friendly.
 */
class ClubReports
{
    /**
     * Monthly recurring revenue in cents: running subscriptions, yearly plans spread over 12 months.
     */
    public function monthlyRecurringRevenueCents(Club $club): int
    {
        $total = Subscription::query()
            ->join('subscription_plans', 'subscription_plans.id', '=', 'subscriptions.subscription_plan_id')
            ->where('subscriptions.club_id', $club->id)
            ->whereIn('subscriptions.status', [SubscriptionStatus::Active, SubscriptionStatus::PastDue])
            ->selectRaw('SUM(CASE WHEN subscription_plans.interval = ? THEN subscription_plans.price_cents / 12.0 ELSE subscription_plans.price_cents END) as mrr', [BillingInterval::Yearly->value])
            ->value('mrr');

        return (int) round((float) $total);
    }

    /**
     * Trial lessons booked in the period and how many of those visitors became a member.
     * A trial counts as converted once the membership is active or paused, or ended
     * after a conversion (joined later than the trial started).
     *
     * @return array{trials: int, converted: int}
     */
    public function trialConversion(Club $club, CarbonInterface $from): array
    {
        $row = Membership::query()
            ->where('club_id', $club->id)
            ->where('trial_started_at', '>=', $from->toDateString())
            ->selectRaw('COUNT(*) as trials')
            ->selectRaw(
                'SUM(CASE WHEN status IN (?, ?) OR (status = ? AND joined_at > trial_started_at) THEN 1 ELSE 0 END) as converted',
                [MembershipStatus::Active->value, MembershipStatus::Paused->value, MembershipStatus::Ended->value],
            )
            ->toBase()
            ->first();

        return ['trials' => (int) ($row->trials ?? 0), 'converted' => (int) ($row->converted ?? 0)];
    }

    /**
     * Per lesson over past, held occurrences in the period: occupancy, check-ins and late cancellations.
     * The no-show rate is only meaningful when the club checks people in, so it is null without check-ins.
     *
     * @return list<array{lesson_id: int, name: string, occurrences: int, booked: int, capacity: int, occupancy: float, check_ins: int, no_show_rate: float|null, late_cancellations: int}>
     */
    public function lessonAttendance(Club $club, CarbonInterface $from): array
    {
        $until = now();

        $occupancy = LessonOccurrence::query()
            ->join('lessons', 'lessons.id', '=', 'lesson_occurrences.lesson_id')
            ->where('lesson_occurrences.club_id', $club->id)
            ->where('lesson_occurrences.starts_at', '>=', $from)
            ->where('lesson_occurrences.starts_at', '<', $until)
            ->where('lesson_occurrences.status', LessonOccurrenceStatus::Scheduled)
            ->groupBy('lessons.id', 'lessons.name')
            ->selectRaw('lessons.id as lesson_id, lessons.name as name, COUNT(*) as occurrences, SUM(lesson_occurrences.booked_count) as booked')
            ->selectRaw('SUM(COALESCE(lesson_occurrences.capacity_override, lessons.capacity)) as capacity')
            ->toBase()
            ->get();

        if ($occupancy->isEmpty()) {
            return [];
        }

        $checkIns = CheckIn::query()
            ->join('lesson_occurrences', 'lesson_occurrences.id', '=', 'check_ins.lesson_occurrence_id')
            ->where('check_ins.club_id', $club->id)
            ->where('check_ins.checked_in_at', '>=', $from)
            ->where('lesson_occurrences.starts_at', '>=', $from)
            ->where('lesson_occurrences.starts_at', '<', $until)
            ->groupBy('lesson_occurrences.lesson_id')
            ->selectRaw('lesson_occurrences.lesson_id as lesson_id, COUNT(*) as check_ins')
            ->toBase()
            ->pluck('check_ins', 'lesson_id');

        $lateCancellations = DB::table('enrollment_absences')
            ->join('lesson_occurrences', 'lesson_occurrences.id', '=', 'enrollment_absences.lesson_occurrence_id')
            ->where('lesson_occurrences.club_id', $club->id)
            ->where('lesson_occurrences.starts_at', '>=', $from)
            ->where('lesson_occurrences.starts_at', '<', $until)
            ->where('enrollment_absences.credit_forfeited', true)
            ->groupBy('lesson_occurrences.lesson_id')
            ->selectRaw('lesson_occurrences.lesson_id as lesson_id, COUNT(*) as late')
            ->pluck('late', 'lesson_id');

        $clubChecksIn = $checkIns->isNotEmpty();

        return array_values($occupancy
            ->map(function (object $row) use ($checkIns, $lateCancellations, $clubChecksIn): array {
                $booked = (int) $row->booked;
                $capacity = (int) $row->capacity;
                $checkInCount = (int) ($checkIns[$row->lesson_id] ?? 0);

                return [
                    'lesson_id' => (int) $row->lesson_id,
                    'name' => (string) $row->name,
                    'occurrences' => (int) $row->occurrences,
                    'booked' => $booked,
                    'capacity' => $capacity,
                    'occupancy' => $capacity > 0 ? round($booked / $capacity * 100, 1) : 0.0,
                    'check_ins' => $checkInCount,
                    'no_show_rate' => $clubChecksIn && $booked > 0 ? round(max(0, $booked - $checkInCount) / $booked * 100, 1) : null,
                    'late_cancellations' => (int) ($lateCancellations[$row->lesson_id] ?? 0),
                ];
            })
            ->sortByDesc('occupancy')
            ->all());
    }
}
